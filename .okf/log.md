---
type: Log
title: jovian/vulkan change log
description: What changed, when, and what it cost to learn.
tags: [log]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Log

## 2026-09-13 — 0.8.0, the package built

Built from scratch by porting `jovian/ogx`'s generator to `ext-vulkan`'s
annotations and `vk.xml`. **267 command methods across 13 classes + 368 struct
value objects × 4 bound methods + 12 hand-written Bridge calls = the
extension's 1751.** 114 enums, 1224 cases, 64 of them bitmask types. 38 enum
parameters, 94 enum returns, 468 enum-typed struct members.

Green on both boxes: every gate and `vendor/bin/pest` (41 tests, 16113
assertions, 0 skipped) on the Mac; `PI_VERIFY_OK` on the Pi with the suite at
41 passed and 16044 assertions there too. `PROOF_HEADLESS_TYPED_OK` on both,
centre `255,128,64,255` and corner `0,0,0,255` — the same bytes ext-vulkan's
own `proof_headless.php` renders on the same box, which `verify-smoke.mjs`
checks by running both and comparing the device line as well as the pixels.

### Design decisions

- **One registry, not two.** ogx's hardest rule — grouping from `gl.xml`,
  values from the extension's own vendored header, never both from one file —
  does not transpose. ext-vulkan's `src/*.{h,c}` are *generated from* `vk.xml`,
  so the registry is not a second opinion about the extension, it is the
  extension's source. Four of ogx's generator classes collapse into one
  `VkRegistry`, and the scope walk is `require`d out of the extension's own
  `scripts/lib/registry.php` rather than re-implemented. Stated in
  [enum-mapping.md](/enum-mapping.md) rule 1.
- **A fourth tier: 368 readonly value objects.** ogx has commands, enums and a
  runtime; Vulkan is a struct-shaped API and the extension's flat
  `pack(array)` tier leaves the key spellings, the member types and the `sType`
  untyped. A value object types all three and adds nothing else — `toArray()`
  is the object flattened, `fromArray()` is its inverse, and both make zero
  extension calls, which the parity gate checks explicitly. See
  [struct-values.md](/struct-values.md).
- **`pack()` and `packInto()` are instance methods.** The object already holds
  its members; handing them back to it would make it a wrapper rather than a
  value. That is the first time this house's projection tier has had a
  non-static method, and it is what made `scanProjection` in the gate library
  need to see both shapes — a scanner that only read `public static function`
  would have missed 736 projected methods and still reported a tidy number.
- **The registry's mask/bits distinction is sharper than ogx's.** `VkImageUsageFlags`
  (`category="bitmask"`) stays `int` because `Bits::A | Bits::B` is a
  `TypeError`; `VkSampleCountFlagBits` (`category="enum"`) is typed, because it
  is one bit chosen from a list rather than a mask. `VkImageCreateInfo` carries
  both spellings next to each other and a test pins the pair.
- **`ApiVersion` is the one added value object in the runtime**, on ogx's
  `ContextVersion` precedent. It does not compare variants, and it says why.
- **No `Buffer` object**, for ogx's reason: a destructor on one owns a
  lifetime, and lifetimes are composition. The struct value objects are not a
  counter-example — they hold member values, never a pointer's lifetime.
- **`unpack(0)` throws a `TypeError`, deliberately.** The extension warns
  `ptr is NULL` and returns `null`; casting that to `[]` would hand back a
  zero-filled object the caller never had, and inventing a value is the one
  thing this layer must never do.

### What the gates caught

- **`Ext` is a prefix of `Extent`.** ogx's parity gate finds extension calls
  with `Ext[A-Za-z0-9_]+::`, which reports `VkExtent3D::fromArray()` as one and
  flagged 18 conversions as composites the first time the gate ran. It matches
  the alias each file actually imported now, which is both precise and a better
  statement of the rule.
- **A `<type>` nests inside a `<type>`.** The enum gate's independent XML
  reader used a non-greedy `<type\b[^>]*>([\s\S]*?)</type>`, which stops at the
  *first* closing tag — so `typedef <type>VkFlags</type>
  <name>VkImageUsageFlags</name>` lost its name and every struct read as zero
  members. `VkImageUsageFlagBits` came back "not reachable" and the gate would
  have agreed with anything. Read with a depth count now.
- **`<name alias="…">` on the promoted feature members.**
  `VkPhysicalDeviceVulkan11Features` spells every member as
  `<name alias="VkPhysicalDevice16BitStorageFeatures::storageBuffer16BitAccess">`,
  and a bare `/<name>/` misses all of them — silently, and only for the four
  `VkPhysicalDeviceVulkanXXFeatures`/`Properties` structs. Caught because the
  gate measured 460 enum-typed members where the generator measured 468, and
  eight is a small enough number to chase.

All three were found by making the gate disagree with the generator and
chasing the difference, which is the whole reason the gate re-derives from
`vk.xml` instead of reading `scripts/Generator/*.json`.

### What the Pi caught

Nothing, this wave — which is itself worth writing down. The generator
reproduced the tree byte for byte on the first run
(`bbd28ede48e461310d70c5fbcc8ae891a26c25d24925695a7f875a5de8fd594e` on both
boxes), the Pest suite and the struct round trip were green, and the typed
proof drew the same two pixels on V3D that it draws on MoltenVK.

That is a different situation from ogx, where the Pi is the only box with an
EGL context and the only box without an Apple SDK. Here both boxes have the
whole registry and both have a real loader; what the Pi adds is a driver that
is not a translation layer. Until it ran, "adding types did not change a byte"
was a claim about MoltenVK.

### Measured, so nobody has to guess

- **Zero reservations.** ext-vulkan's type table covers every command in scope,
  so `reserved=0`. The parity gate's counting control fabricates a command
  rather than counting a `@reserved` line, and says so.
- **Zero value collisions.** The registry spells its synonyms with `alias=`,
  which is skipped outright; no second *name* carries an already-used *value*.
  The recording mechanism is live and reads whatever is there.
- **Zero naming fallbacks.** The prefix ladder reaches all 1224 cases.
- **Zero escaped parameter names.** `gen-zep.php` still applies Zephir's rule,
  but nothing in today's registry hits it, so the two layers spell every
  parameter identically — including for named arguments. ogx needs a whole
  rule about this; here it is a measurement.
- **Ten enums skipped and recorded**, each a `*FlagBits` type whose every bit
  comes from an extension this binding does not cover.
- **Two `sType`s left at `0`**: `VkBaseInStructure` and `VkBaseOutStructure`,
  because the registry gives them no `values=` and any `sType` is legal there.

### Open

- `venusian` gets the composition layer, and with it whatever owns a block's
  lifetime.
- A `@reserved` line appearing in ext-vulkan is news: the parity gate will say
  `PARITY_RESERVED_APPEARED`, and the rule is that a reservation is not a
  binding and must not be projected.
- `VkAccessFlagBits2` carries a `bitpos="63"` in an extension marked
  `supported="disabled"`. If that extension is ever bound, the generator will
  stop by name rather than overflow into a negative PHP int.
