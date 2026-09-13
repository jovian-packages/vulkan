# Agent guidelines — jovian/vulkan

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/)
(excluded from the Composer dist via `.gitattributes` `export-ignore`).
Before changing code or advising on this package: read
[`.okf/index.md`](.okf/index.md) first, open only the concepts the task
needs, prefer `status: stable` over `draft`. When you learn something
durable, update the affected concept(s) and append
[`.okf/log.md`](.okf/log.md); new or changed concepts stay `status: draft`
until a human verifies them.

## Where this package sits

`ext-vulkan` (1:1 binding + unavoidable glue) → **`jovian/vulkan`** (enums,
typed projection, struct value objects) → `venusian` (composition) → Surface
(cross-platform abstraction). Never reach up the stack, and never build a
cross-platform abstraction here — that is Surface's job.

## Rules

1. **Projection, not composition.** A method is legitimate only if it is
   exactly one extension call with the same arguments in the same order. The
   test: *can it be written as one extension call?* If not, it belongs in
   `venusian`. `tests/Generator/GenerateCheckTest.php` and
   `scripts/gates/verify-parity.mjs` both enforce it: each of the 1739
   generated bodies makes exactly one call to the extension class its own file
   imported — and the 736 `toArray()`/`fromArray()` conversions make exactly
   **zero**, because a constructor and its inverse fetch nothing.
2. **Never hand-edit generated code.** `src/VK/**`, `src/Ext/**`,
   `src/Structs/**` and `src/Enums/**` come from
   `php scripts/generate.php --ext PATH`, which joins `ext-vulkan`'s `@zep`
   annotations against `vk.xml`. Edit the generator, then regenerate.
3. **Hand-written code is `src/Runtime/Bridge.php` and
   `src/Values/ApiVersion.php`.** That is the whole list, and
   `verify-style.mjs` fails if a third file appears.
4. **One registry, for grouping *and* values.** ogx must take grouping from
   `gl.xml` and values from the extension's own vendored header, because
   taking both from the registry would let the two packages drift.
   ext-vulkan's `src/*.{h,c}` are *generated from* `vk.xml`, so the registry is
   the extension's source rather than a second opinion about it, and one file
   answers both questions. Read
   [`.okf/enum-mapping.md`](.okf/enum-mapping.md) before touching any of it.
5. **Reuse the extension's registry walk; never copy it.**
   `VkRegistry::load()` `require`s `ext-vulkan`'s own
   `scripts/lib/registry.php`. A generator that re-implemented
   `vkRegistryScope()` would join against the re-implementation. What this
   package reads for itself is only the half that walk does not carry, because
   the extension binds no constants: the `<enums>` blocks, the
   `<enum extends=…>` entries, the `requires=`/`bitvalues=` link and the
   `values=` on a member.
6. **A registry type becomes an enum only when the bound surface reaches it,**
   and a reachable type with no in-scope case is **recorded** in
   `scripts/Generator/enum-names.json`, never silently dropped. Ten are, today.
7. **A `VkFlags` mask stays `int` on both sides.** `Bits::A | Bits::B` is a
   `TypeError` in PHP. The `FlagBits` enum is still emitted — the mask is how
   it becomes reachable — and the signature is still `int`. A slot the registry
   declares as the `FlagBits` type itself carries one bit, not a mask, and IS
   typed: `VkImageCreateInfo` has `usage` (`int`) and `samples`
   (`VkSampleCountFlagBits|int`) next to each other.
8. **An enum-typed return is documentation, and returns an `int`.**
   `vkCreateInstance(): VkResult|int` hands back an int, because converting
   would be behaviour. `VkResult::tryFrom($r)` is the idiom;
   `=== VkResult::SUCCESS` is always false. Inherited from `jovian/metal` via
   `jovian/ogx` on purpose — diverging from the sibling packages would be worse
   than the wart.
9. **Enums are int-backed with FULLY UPPERCASE cases. No class constants
   anywhere.** Prefer `is_null($x)` over `$x === null`. `declare(strict_types=1)`
   on every file, the GENERATED banner on everything the generator owns. Gate:
   `verify-style.mjs`. The case name comes off a prefix *ladder*, and a stem
   that starts with a digit keeps the prefix's last word:
   `VK_SAMPLE_COUNT_1_BIT` → `COUNT_1_BIT`.
10. **A value may appear once per enum.** A registry `alias=` entry is the
    registry's own synonym and is skipped; a second *name* carrying an
    already-used *value* is recorded in the docblock and in `enums.json`, never
    dropped. There are none today, and that is measured rather than assumed.
11. **A struct value object is the typed shape of the extension's array, and
    nothing more.** `toArray()` is the object flattened, `fromArray()` is its
    inverse, the four bound methods are one extension call each, and
    `ext-vulkan`'s [`.okf/struct-tier.md`](../../php-io-extensions/vulkan/vulkan/.okf/struct-tier.md)
    member table wins over any assumption about a member's shape. A missing key
    packs as zero because the extension `memset`s first — never invent a
    default the registry did not name. Read
    [`.okf/struct-values.md`](.okf/struct-values.md).
12. **Replay the extension's FQCN rule, do not derive it.** `gen-zep.php`
    collapses a duplicate tail: `VK\VK10` is `Vulkan\VK\VK10\VK10` and
    `Struct\VkExtent2D` is `Vulkan\Struct\VkExtent2D\VkExtent2D`, but
    `Bridge\Bridge` is `Vulkan\Bridge\Bridge` — not a fourth segment. Getting
    it wrong emits `use` lines for classes that do not exist, and PHP will not
    tell you until something calls one.
13. **Counting the surface? `@zep` only, and `@reserved` is not a binding.**
    1751 `@zep` = 267 commands + 368×4 struct methods + 12 Bridge. ext-vulkan
    reserves nothing today; the day it does, `verify-parity.mjs` prints
    `PARITY_RESERVED_APPEARED` and the reserved command must **not** be
    projected.
14. **No `PHP_OS_FAMILY` in `src/`, ever.** Every WSI class exists on both
    boxes because ext-vulkan resolves everything at runtime, and this package
    chooses neither. Callers choose — the proof, the Pest bootstrap, and one
    test, all for the same reason: MoltenVK reports zero devices without
    `VK_KHR_portability_enumeration`, which is the driver's rule.

## Verification

```bash
export HERD_PHP_84_INI_SCAN_DIR=$(zsh -ic 'echo $HERD_PHP_84_INI_SCAN_DIR')   # Mac

php scripts/generate.php --check               # commands=267 structs=368 … GEN_OK
node scripts/gates/verify-generate.mjs         # GEN_OK
node scripts/gates/verify-parity.mjs           # PARITY_OK          1751 = 1751
node scripts/gates/verify-enum-typing.mjs      # ENUM_TYPING_OK     114 enums, 1224 cases
node scripts/gates/verify-return-typing.mjs    # RETURN_TYPING_OK   94 enum, 368 self
node scripts/gates/verify-style.mjs            # STYLE_OK
node scripts/gates/verify-structs.mjs          # STRUCTS_OK         needs ext-vulkan
node scripts/gates/verify-reflection.mjs       # REFLECTION_OK      needs ext-vulkan
node scripts/gates/verify-live.mjs             # LIVE_OK            needs ext-vulkan
node scripts/gates/verify-smoke.mjs            # SMOKE_OK           needs a device
node scripts/gates/verify-platform-control.mjs reflection   # PLATFORM_CONTROL_CONSISTENT
node scripts/gates/verify-platform-control.mjs structs      # PLATFORM_CONTROL_CONSISTENT
node scripts/gates/verify-platform-control.mjs live         # PLATFORM_CONTROL_CONSISTENT
node scripts/gates/verify-platform-control.mjs smoke        # PLATFORM_CONTROL_CONSISTENT
vendor/bin/pest                                # 41 passed
php examples/proof_headless_typed.php          # PROOF_HEADLESS_TYPED_OK
bash scripts/pi-verify.sh                      # PI_VERIFY_OK
```

Every extension-dependent test skips when `ext-vulkan` is absent, so a green
run without it proves less than it looks like. That is what the platform
control gate exists to catch — a skipped suite reporting success.

**A wave is not done until `scripts/pi-verify.sh` is green**, and not just
because Linux is a second compiler. The Mac only ever sees MoltenVK, a
translation layer over Metal; the Pi is the only box with a real Vulkan driver
on real hardware, and the only proof that the generator reproduces the
committed tree from a second copy of the registry. Fix on the Mac, re-push —
never edit on the Pi.

When you add a gate, give it a positive control: deliberately break the thing
it checks, watch it fail, then restore. A gate that has never failed is not
yet evidence. Two of this package's gates found real bugs in themselves that
way — see [`.okf/log.md`](.okf/log.md).
