---
type: Component
title: The struct value objects
description: >-
  368 readonly value objects, one per registry struct or union: the typed shape
  of the array ext-vulkan's pack() already takes and unpack() already returns.
  The member table, the defaults, unions, nesting, and what the round-trip gate
  actually proves.
resource: src/Structs
tags: [struct, value-object, pack, unpack, readonly, vulkan]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Struct value objects

Vulkan is a struct-shaped API: almost every command takes a pointer to one.
`ext-vulkan`'s answer is a **flat** tier — one class per registry struct, four
methods, and a PHP array of members, marshalled by a table with no judgement
in it. That is the right answer for a binding, and it leaves the caller
writing this:

```php
VkImageCreateInfo::pack([
    'sType' => VK_STRUCTURE_TYPE_IMAGE_CREATE_INFO,
    'imageType' => VK_IMAGE_TYPE_2D,
    'extent' => ['width' => 64, 'height' => 64, 'depth' => 1],
]);
```

Three things there are untyped and unchecked: the key spellings, the member
types, and the `sType`. This package types all three, and adds nothing else:

```php
(new VkImageCreateInfo(
    imageType: VkImageType::TYPE_2D,
    extent: new VkExtent3D(width: 64, height: 64, depth: 1),
))->pack();
```

**368 classes, 2 of them unions, 2296 members, 1472 bound methods and 736
conversions.** `VkPhysicalDeviceLimits` is the largest at 106 members; none is
empty.

## The shape

```php
final readonly class VkApplicationInfo
{
    public function __construct(
        public VkStructureType|int $sType = VkStructureType::APPLICATION_INFO,
        public int $pNext = 0,
        public int $pApplicationName = 0,
        public int $applicationVersion = 0,
        public int $pEngineName = 0,
        public int $engineVersion = 0,
        public int $apiVersion = 0,
    ) {}

    public function toArray(): array;          // this object, flattened
    public static function fromArray(array $members): self;   // its inverse

    public function pack(): int;               // one extension call
    public function packInto(int $ptr): void;  // one extension call
    public static function unpack(int $ptr): self;   // one extension call
    public static function size(): int;        // one extension call
}
```

`jovian/metal`'s `MTLSize` is the shape being followed: promoted readonly
properties, a constructor that is the whole of the object, and a pair of array
conversions that are the constructor read in both directions.

`pack()` and `packInto()` are **instance** methods because the object already
holds its members; `unpack()` and `size()` are static because neither needs
one. The arity difference from the extension's four — which are all static and
all take the member array — is exactly the `$this` the value object supplies.
`scripts/gates/reflect.php` checks both sides of that.

## The member table

ext-vulkan's own table
([`.okf/binding-rules.md` §Table 2](../../../php-io-extensions/vulkan/vulkan/.okf/binding-rules.md)),
read as PHP types instead of as C. The counts are measured, not estimated:

| registry member | PHP | default | count |
|---|---|---|---:|
| every pointer, `pNext`, `PFN_*`, handles, integers, enums, `VkFlags*`, `VkDeviceSize` | `int` | `0` | 1715 |
| `VkBool32` | `bool` | `false` | 431 |
| struct or union **by value** | `?VkThing` | `null` | 83 |
| `float`, `double` | `float` | `0.0` | 25 |
| `T[N]` of scalars | `array` | `[]` | 24 |
| `char[N]` | `string` | `''` | 12 |
| `T[N]` of structs | `array` of value objects | `[]` | 6 |

468 of the 1715 `int` members are additionally typed `SomeEnum|int` by
[enum-mapping.md](/enum-mapping.md) rule 5 — one value, never a mask.

## The four rules a reader will trip on

**A nested struct defaults to `null`, and `null` means absent.** PHP allows
`new` in a parameter default but **not** in a property default, and a promoted
constructor parameter is both — so `public ?VkExtent3D $extent = new VkExtent3D()`
does not compile. The default is `null`, `toArray()` leaves the key out
entirely, and the extension zero-fills it: `memset` runs before every fill, so
a missing key **is** zero rather than a default this layer invented. That is
the extension's rule, unchanged.

**`sType` defaults to the registry's own `values=`, and only that.** 296 of the
368 structs carry `<member values="VK_STRUCTURE_TYPE_…">`, which is `vk.xml`
naming the one constant that member may hold; those default to the enum case.
`VkBaseInStructure` and `VkBaseOutStructure` carry none — they are the generic
`pNext` chain heads and any `sType` is legal — so they default to `0`, and the
choice is written down in `scripts/Generator/enum-names.json`.
`scripts/gates/verify-structs.mjs` re-derives all 296 from the registry and
fails on a default that is not the one `values=` says.

**A union's members are all nullable and all default to `null`.** Only the one
you wrote has a meaning. `toArray()` emits the members that are not null, in
registry order, and the last one written wins — the extension's own union rule.
`unpack()` hands back every member, because which one is live is not knowable
from the bytes:

```php
$clear = new VkClearValue(color: new VkClearColorValue(float32: [0.0, 0.0, 0.0, 1.0]));
$clear->toArray();   // ['color' => ['float32' => [0.0, 0.0, 0.0, 1.0]]] — no depthStencil
```

**An enum-typed member unpacks as an `int`.** `fromArray()` casts to `int` and
the property is `VkStructureType|int`, so a round trip through the object gives
you back the number the memory held. Converting would be behaviour
([enum-mapping.md](/enum-mapping.md) rule 8). `VkStructureType::tryFrom($x)` is
the idiom.

## What the round-trip gate proves

`scripts/gates/structs.php`, driven by `verify-structs.mjs`, does this for
**every one of the 368**, live, on both boxes:

1. fill a block with a deterministic pattern and `unpack` it with the
   **extension's** own method — that array is the array of record;
2. `fromArray()` it and `toArray()` it back; the result must be `===` identical,
   keys, order and types included;
3. `pack()` the object and `unpack` that with the extension again; the members
   must come back the same, so nothing was lost on the way out;
4. `packInto()` a `size()`-byte block and compare it byte for byte with what
   `pack()` wrote, so an array of value objects strides right.

`STRUCTS_MEASURED structs=368 members_round_tripped=2296`. No registry is
consulted: the claim is that the object and the extension agree with each
other, and a third opinion would only give the test something else to be wrong
about. The control drops one member from step 2 and must be seen failing.

The fill is deliberate rather than random. A NUL every eighth byte keeps every
`char[N]` short enough to pack back — a string of exactly `N` characters needs
`N+1` bytes and the extension refuses it by name — and capping the other bytes
at 61 keeps every `float` and `double` finite, because a NaN compares unequal
to itself and would fail a passing round trip for a reason that has nothing to
do with this package.

## Ownership, which is still the caller's

- **`pack()` returns a tracked block you free.** `Bridge::free($ptr)`, or the
  module teardown sweeps it. The value object does not own it, does not
  remember it, and has no destructor.
- **`packInto()` owns nothing** and writes exactly the bytes `pack()` would
  have written into its own block. That is what makes an array work:
  ```php
  $block = Bridge::alloc(2 * VkViewport::size());
  (new VkViewport(width: 1.0))->packInto($block);
  (new VkViewport(width: 2.0))->packInto($block + VkViewport::size());
  ```
- **`unpack()` copies.** The object shares nothing with the memory it read.

A value object that owned a lifetime would be composition, and that is
`venusian`'s job — see [runtime.md](/runtime.md) on the `Buffer` that
deliberately does not exist. The difference is that a `Structs\*` holds member
*values*, never a pointer's lifetime.

## `unpack(0)` throws, and that is the design

The extension warns `ptr is NULL` and returns `null`; the projection is one
call and hands that to `fromArray(array $members)`, so PHP raises a
`TypeError`. `unpack()` is declared `: self` and **there is no struct at
address 0**. Casting the null to `[]` would hand back a zero-filled object the
caller never had. See [projection-rule.md](/projection-rule.md).
