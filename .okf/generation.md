---
type: Component
title: scripts/generate.php — the annotation × registry join
description: >-
  jovian/ogx's generator, ported. What changed for Vulkan, what each gate
  proves, and the two traps that cost real time.
resource: scripts/generate.php
tags: [generator, gates, join, vulkan]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Generation

```bash
php scripts/generate.php --check     # join everything, emit nothing
php scripts/generate.php             # join and emit
```

Success line:

```
commands=267 classes=381 structs=368 members=2296 struct_methods=1472 bridge=12
reserved=0 enums=114 skipped_enums=10 cases=1224 bitmask_enums=64
enum_params=38 enum_returns=94 enum_members=468 GEN_OK
```

`--ext` is the `ext-vulkan` checkout (`JOVIAN_VULKAN_EXT`); its `src/*.h` is
the authoritative list of what exists and its `scripts/khronos/vk.xml` is the
authoritative list of what every name is and what every value is worth.

**An annotation that does not join is a hard failure.** The generator never
skips a name it cannot place.

## What changed from jovian/ogx

The *shape* is identical: `scripts/generate.php` + `scripts/Generator/*`, a
`Pipeline` that joins or dies, committed join sidecars, an `Emitter` that owns
every generated file. Four things are different, all forced by the subject
matter.

1. **One registry instead of two.** ogx needs `gl.xml` for grouping and the
   extension's vendored `glcorearb.h` for values, because taking values from
   the registry would let the two packages drift. ext-vulkan's `src/*.{h,c}`
   are *generated from* `vk.xml`, so the registry is not a second opinion — it
   is the extension's source, and `GlRegistry` + `HeaderConstants` +
   `EnumMiner` + `CglJoin` collapse into one `VkRegistry`. See
   [enum-mapping.md](/enum-mapping.md) rule 1.
2. **The walk is borrowed, not rewritten.** `VkRegistry::load()` `require`s
   ext-vulkan's own `scripts/lib/registry.php` and calls `vkRegistryLoad()`,
   `vkRegistryScope()` and `vkArrayLength()`. A generator that re-implemented
   the walk would join against the re-implementation. What it adds is the half
   that walk does not carry, because the extension binds no constants: the
   `<enums>` blocks, the `<enum extends=…>` entries, the `requires=` /
   `bitvalues=` link from a mask to its bits, and the `values=` on a member.
3. **A fourth tier.** `StructEmitter` turns 368 registry structs into readonly
   value objects. ogx has no analogue — OpenGL has no structs crossing the
   boundary. See [struct-values.md](/struct-values.md).
4. **Nothing boxes, again.** metal's hardest rule — when a return becomes an
   object and which class it becomes — has no analogue here either. A Vulkan
   handle is pointer bits and pointer bits are an `int`. The one return that
   changes shape is `Structs\*::unpack()`, and the class is the one the method
   is on.

## Files

| | |
|---|---|
| `Annotation` / `AnnotationParser` | the `/*@zep …*/` lines, and the `@reserved` count |
| `VkRegistry` | `vk.xml`: scope (borrowed), enum blocks and member `values=` (read here) |
| `TypeJoin` | one annotation × its declaration → one typed signature; one member → one typed slot |
| `TypeName` | the scalar table, the prefix ladder and the digit rule |
| `EnumDefinition` / `JoinedMethod` | what the emitter is handed |
| `StructEmitter` | one struct → one readonly value object |
| `Emitter` | writes `src/**`, the sidecars, and prunes what the join no longer produces |
| `Pipeline` | reachability, the join, the counts, `--check` |

Sidecars, all committed: `joined-methods.json` (267 signatures),
`enums.json` (114 enums), `enum-names.json` (the naming rule, its fallbacks,
the skipped enums and the two undefaulted `sType`s), `structs.json` (368
structs, 2296 members).

## Gates

Every gate has a positive control that has been watched failing. A gate nobody
has watched fail is not yet evidence.

```bash
export HERD_PHP_84_INI_SCAN_DIR=$(zsh -ic 'echo $HERD_PHP_84_INI_SCAN_DIR')   # Mac

php scripts/generate.php --check --ext …          # GEN_OK
node scripts/gates/verify-generate.mjs            # GEN_OK            join + counts + idempotence
node scripts/gates/verify-parity.mjs              # PARITY_OK         267 + 1472 + 12 = 1751
node scripts/gates/verify-enum-typing.mjs         # ENUM_TYPING_OK    re-derived from vk.xml
node scripts/gates/verify-return-typing.mjs       # RETURN_TYPING_OK
node scripts/gates/verify-style.mjs               # STYLE_OK
node scripts/gates/verify-structs.mjs             # STRUCTS_OK        needs ext-vulkan
node scripts/gates/verify-reflection.mjs          # REFLECTION_OK     needs ext-vulkan
node scripts/gates/verify-live.mjs                # LIVE_OK           needs ext-vulkan
node scripts/gates/verify-smoke.mjs               # SMOKE_OK          needs a device
node scripts/gates/verify-platform-control.mjs reflection|live|structs|smoke
vendor/bin/pest                                   # 41 passed
php examples/proof_headless_typed.php             # PROOF_HEADLESS_TYPED_OK
bash scripts/pi-verify.sh                         # PI_VERIFY_OK
```

| gate | control |
|---|---|
| GEN | a scratch mirror of the extension's headers with `vkCreateInstance` and `Struct\VkExtent2D` renamed to names the registry does not declare; both must fail to join |
| PARITY | (1) demands a command the headers do not annotate; (2) drops `VK10::vkCreateInstance` and `VkExtent2D::packInto` |
| RETURN | (1) remaps the extension's `int` and `void` returns to `string` and `int`; (2) expects `unpack()` to hand back the extension's raw `array` |
| ENUM | asserts `VkImageUsageFlagBits` is emitted, is reachable through `VkImageUsageFlags`, and that `VkImageCreateInfo.usage` is **not** typed with it |
| STYLE | five probes prove the checks can see a lowercase case, a `=== null`, a class constant, a missing `strict_types` and a `PHP_OS_FAMILY` in `src/` |
| STRUCTS | (1) a struct with no value object; (2) `VkApplicationInfo`'s members reversed; (3) an `sType` default that is not the registry's `values=`; (4) live, a round trip that drops a member |
| REFLECTION | reflects on class paths that cannot exist |
| SMOKE | runs ext-vulkan's *own* proof too, and fails on one differing pixel or a different device |
| PLATFORM_CONTROL | a stamp that says "skipped" while the extension is loaded is a failure |

`SMOKE_OK` is the one that matters most: it renders both proofs on the same
box and compares the picture. Adding types must not change a byte.

## Two traps that cost real time

**`Ext` is a prefix of `Extent`.** ogx's parity gate counts `Ext[A-Za-z0-9_]+::`
to find extension calls, which works there because no OpenGL type is called
`Extent`. Here it reports `VkExtent3D::fromArray()` as an extension call and
flags 18 conversions as composites. The gate matches the alias each file
actually imported instead — `use Vulkan\Struct\VkExtent3D\VkExtent3D as
ExtVkExtent3D;` — which is both precise and a better statement of the rule.

**A `<type>` nests inside a `<type>`.** `typedef <type>VkFlags</type>
<name>VkImageUsageFlags</name>` and every struct member put a `<type>` element
inside the `<type>` element that declares them, so a non-greedy
`<type\b[^>]*>([\s\S]*?)<\/type>` stops at the *first* closing tag: every
bitmask typedef loses its name and every struct reads as zero members. The
enum gate then agrees with anything. It is read with a depth count now. The
same file also carries `<name alias="…">` on the promoted 1.1/1.2/1.3 feature
members, which a bare `/<name>/` misses — silently, and only for those.

Both were found by making the gate disagree with the generator and chasing the
number, which is the whole reason the gate re-derives instead of reading the
sidecar.

## Mac / Pi

Generation and every gate run on the Mac — the `.mjs` oracles need `node`, and
the Pi has none. The Pi does not need them: `scripts/pi-verify.sh` pushes the
package, runs `composer install` there, regenerates **from the Pi's own copy
of `vk.xml`**, digests the result against the tree that left the Mac, and runs
the Pest suite, the struct round trip, reflection and the typed proof.

ogx needs `cgl-join.json` for this because the Pi has no Apple SDK to mine
CGL's enums from, so a sidecar stands in for a missing input. Here `vk.xml` is
the whole input and both boxes have it, which makes the digest the stronger
claim it looks like: two machines, two operating systems, one registry,
byte-identical output. Fix on the Mac, re-push — never edit on the Pi.
