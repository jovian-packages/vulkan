---
type: Convention
title: The enum mapping rules
description: >-
  vk.xml is the one registry — it says what every name is AND what every value
  is worth — so jovian/ogx's two-file rule collapses to one. This is the
  complete, mechanical rule for turning it into PHP enums: which types become
  enums, which constants become cases, how a case is named, and what is
  deliberately left as int.
resource: src/Enums
tags: [enums, registry, vk.xml, khronos, vulkan]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Enum mapping

**114 enums, 1224 cases, 64 of them bitmask types, 10 reachable types skipped
and recorded.**

`ext-vulkan` binds no constants at all, on purpose: its rule 10 says "enum
values become PHP enums in jovian/vulkan", and its own proof carries 74 inline
`const VK_* = …; // vk.xml` literals *because this package did not exist yet*.
Those literals are what these enums replace, and
`tests/Enums/RegistryParityTest.php` pins every one of them by name.

This is the only place `jovian/vulkan` needs judgement, so the judgement is
spent once, here, on making the rules mechanical — and then the generator
follows them without a second opinion. **Nothing in `src/Enums/` was named or
grouped by hand.**

## Rule 1 — one registry, for grouping *and* values

jovian/ogx has a rule that grouping and values must come from different files,
because `glcorearb.h` is a flat `#define` list with no types and the registry
that *has* the types is a different document from the one the extension was
generated against. Taking values from the registry there would let the two
packages drift.

**Here there is nothing to drift from.** `ext-vulkan`'s `src/*.{h,c}` are
themselves generated from `scripts/khronos/vk.xml` by its own
`gen-vk-src.php`, so the registry is not a second opinion about the extension —
it *is* the extension's source. One file answers both questions, and the
generator reads the extension's own copy rather than vendoring a second one:

```bash
php scripts/generate.php --ext ../../php-io-extensions/vulkan/vulkan
```

The scope walk is `require`d out of the extension's `scripts/lib/registry.php`
rather than re-implemented, for the same reason ext-vulkan's own audit shares
it: a reader that re-implements the walk audits the re-implementation.
`scripts/gates/verify-enum-typing.mjs` then re-derives every enum from the
same XML with an independent parser, in a different language, reading no
sidecar of ours — because a gate that trusted the generator's JSON could only
ever agree with the generator's own mistake.

## Rule 2 — a type becomes an enum only when the bound surface reaches it

An `<enums type="enum">` or `<enums type="bitmask">` block is emitted when its
type name is the declared type of

- a bound command's by-value parameter, or
- a bound command's return, or
- a scoped struct or union's by-value member,

**through a `VkFlags` typedef where there is one**. `VkImageCreateInfo.usage`
is declared `VkImageUsageFlags`, and

```xml
<type requires="VkImageUsageFlagBits" category="bitmask">typedef <type>VkFlags</type> <name>VkImageUsageFlags</name>;</type>
```

is how the registry says which bits that mask holds. So the mask makes
`VkImageUsageFlagBits` **exist** even though no slot is ever typed with it
(rule 5). The 64-bit spelling uses `bitvalues=` instead of `requires=`; both
are followed.

A pointer or an array never reaches anything: it is pointer bits under
ext-vulkan's pointer rule, and typing it would be a lie about what the caller
passes.

## Rule 3 — membership is the block plus the scoped extensions

An enum's cases are

- every `<enum>` in its own `<enums>` block, plus
- every `<enum extends="X">` declared by a **scoped** feature or extension,

minus `alias=` entries, minus any name whose value a case already carries.

"Scoped" is the extension's own decision, read out of its
`scripts/lib/registry.php`: `VK_VERSION_1_0` .. `1_4` (with the registry's
internal `VK_BASE_/VK_COMPUTE_/VK_GRAPHICS_` sub-blocks) and the eleven
extensions `VK_EXTENSIONS` names. The enum set therefore tracks the binding,
not the registry: `vk.xml` declares 354 enum blocks, and 114 of them are
reachable from what ext-vulkan binds.

An extension entry's value is `value` if it has one, else
`1000000000 + (extnumber - 1) * 1000 + offset`, negated when `dir="-"`, with
`extnumber` defaulting to the enclosing extension's `number`. A `bitpos` is
`1 << bitpos`, and **a `bitpos` of 63 or more is a hard failure naming the
enum** rather than a silent overflow into a negative PHP int. Nothing in scope
has one; `VK_ACCESS_2_RESERVED_63_BIT_EXT` does, and it lives in an extension
marked `supported="disabled"`.

## Rule 4 — a reachable type with no in-scope case is skipped and recorded

Ten types are reachable and define **not one constant** inside Vulkan 1.0 .. 1.4
plus the eleven bound extensions. They are skipped and written down in
`scripts/Generator/enum-names.json`, never silently dropped, and their slots
stay plain `int`:

| type | reached by |
|---|---|
| `VkImageViewCreateFlagBits` | `VkImageViewCreateInfo.flags` |
| `VkMemoryMapFlagBits` | `vkMapMemory.flags` |
| `VkMemoryUnmapFlagBits` | `VkMemoryUnmapInfo.flags` |
| `VkPipelineColorBlendStateCreateFlagBits` | `VkPipelineColorBlendStateCreateInfo.flags` |
| `VkPipelineDepthStencilStateCreateFlagBits` | `VkPipelineDepthStencilStateCreateInfo.flags` |
| `VkPipelineLayoutCreateFlagBits` | `VkPipelineLayoutCreateInfo.flags` |
| `VkQueryPoolCreateFlagBits` | `VkQueryPoolCreateInfo.flags` |
| `VkRenderPassCreateFlagBits` | `VkRenderPassCreateInfo.flags` |
| `VkSamplerCreateFlagBits` | `VkSamplerCreateInfo.flags` |
| `VkSubpassDescriptionFlagBits` | `VkSubpassDescription.flags` |

Each is a `*FlagBits` type whose every bit comes from an extension this
binding does not cover. That is ext-vulkan's "nothing is silently omitted"
rule carried up a layer.

## Rule 5 — a mask stays `int` on both sides

A `VkFlags` typedef is **never** used to type a slot, because

```php
VkImageUsageFlagBits::TRANSFER_SRC_BIT | VkImageUsageFlagBits::COLOR_ATTACHMENT_BIT
```

is a `TypeError` in PHP. You write `->value | ->value`. The enum is still
emitted; the signature is still `int`. This is `jovian/metal`'s `NS_OPTIONS`
rule, transposed twice.

The registry's own distinction is what decides it, and it is sharper than
ogx's:

| registry declares | PHP | why |
|---|---|---|
| `VkImageUsageFlags` (`category="bitmask"`) | `int` | a mask: any number of bits |
| `VkSampleCountFlagBits` (`category="enum"`) | `VkSampleCountFlagBits\|int` | one bit, chosen from a list |
| `VkFormat` (`category="enum"`) | `VkFormat\|int` | one value |

`VkImageCreateInfo` carries both spellings next to each other — `usage` is a
mask and stays `int`, `samples` is a single `VkSampleCountFlagBits` and is
typed — and `tests/Enums/EnumShapeTest.php` pins exactly that pair.
27 slots in the package are typed with a bitmask enum this way; not one is a
mask.

## Rule 6 — case names, mechanically

The case name is the constant with its type's prefix removed, uppercased. The
prefix comes off a **ladder**, longest rung first:

1. the type name split on capitals, upper-cased, joined with `_`, plus `_` —
   `VkSampleCountFlagBits` → `VK_SAMPLE_COUNT_FLAG_BITS_`;
2. the same with a trailing `FLAG_BITS`, `BITS` or `FLAGS` removed —
   `VK_SAMPLE_COUNT_`;
3. each of those again with a trailing vendor tag moved or removed, because
   the constants carry it at the *end*:
   `VkDebugUtilsMessageSeverityFlagBitsEXT` reaches
   `VK_DEBUG_UTILS_MESSAGE_SEVERITY_VERBOSE_BIT_EXT` only once both
   `FLAG_BITS` and `EXT` have come off the front half;
4. `VK_`, which is where `VkResult::SUCCESS` comes from.

A run of capitals is **one word**: `EXT` must not become `E_X_T`, or every
vendor-suffixed enum is unnameable.

**If the stripped stem starts with a digit, the prefix's last word goes back
on the front**, because `1_BIT` is not a legal PHP identifier:

```php
VkSampleCountFlagBits::COUNT_1_BIT   // VK_SAMPLE_COUNT_1_BIT
VkImageType::TYPE_2D                 // VK_IMAGE_TYPE_2D
VkImageViewType::TYPE_2D             // VK_IMAGE_VIEW_TYPE_2D
```

The ladder reaches **all 1224 cases**; there are no fallbacks today, and
`scripts/Generator/enum-names.json` records any that ever appear. Two further
rungs exist below the ladder — the longest shared run of UPPER_SNAKE words,
then the whole constant uppercased — so a constant can never be dropped for
want of a name.

## Rule 7 — a value may appear once

A PHP backed enum cannot carry two cases with one value. Registry order wins,
and a second **name** carrying an already-used **value** is recorded in the
enum's docblock and in `scripts/Generator/enums.json`, never dropped.

**There are none today, and that is a fact about `vk.xml` rather than about
this package.** The registry spells its synonyms with `alias=`, which is the
registry saying two names mean one constant, and those are skipped outright —
`VK_ERROR_OUT_OF_POOL_MEMORY_KHR` is an alias of
`VK_ERROR_OUT_OF_POOL_MEMORY`, not a collision. The recording mechanism is
live and `tests/Enums/EnumShapeTest.php` reads whatever is there.

## Rule 8 — an enum-typed return is documentation, and returns an `int`

94 methods are declared `VkResult|int`. **They return an `int`.**

That is deliberate and inherited: `jovian/ogx` declares `ErrorCode|int` and
`jovian/metal` declares `MTLCommandBufferStatus|int` for the same reason.
Converting would be *behaviour*, and this layer adds none. The signature
carries the information — *this int is a `VkResult`* — and the caller does the
conversion:

```php
$result = VK10::vkCreateInstance($info, 0, $out);   // int
VkResult::tryFrom($result);                          // VkResult|null  ← the idiom
$result === VkResult::SUCCESS;                       // always false   ← the trap
```

All 94 are `VkResult`; nothing else in the bound surface returns a by-value
enum type. 38 parameters and 468 struct members are enum-typed on the way in,
where a case and its `->value` are the same call because the projection
unwraps with `instanceof \BackedEnum`.

## What the registry does not type

`vk.xml` types what it types. Where a member is a bare `uint32_t` — every
`memoryTypeBits`, every `queueFamilyIndex` — **the slot stays `int`** and no
type is invented to fill the hole. Three of the extension proof's 74 literals
are like that and stay literals in the typed proof:

- `VK_SUBPASS_EXTERNAL` and `VK_WHOLE_SIZE` live in
  `<enums name="API Constants">`, which is neither `type="enum"` nor
  `type="bitmask"`, so rule 2 emits nothing for them. They keep their citation.
- `VK_COLOR_COMPONENT_RGBA_BITS` was never a registry constant at all: it is
  the proof's own name for four `VkColorComponentFlagBits` bits OR'd together,
  and the typed proof writes it as the OR it always was.
