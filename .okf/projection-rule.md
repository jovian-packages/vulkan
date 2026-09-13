---
type: Convention
title: The projection rule
description: >-
  One PHP method is exactly one extension call, with the same arguments in the
  same order. What that permits, what it forbids, why a value object with
  state is still inside it, and the three places this package touches a value.
tags: [projection, layering, vulkan, struct-tier]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Projection, not composition

A method in this package is legitimate only if it is **exactly one extension
call with the same arguments in the same order.** The test is: *can it be
written as one extension call?* If not, it belongs in `venusian`.

`tests/Generator/GenerateCheckTest.php` and `scripts/gates/verify-parity.mjs`
both enforce this structurally: every one of the **1739** generated bodies
contains exactly one call to the extension class its own file imported.

```php
public static function vkCmdBindPipeline(int $commandBuffer, VkPipelineBindPoint|int $pipelineBindPoint, int $pipeline): void
{
    ExtVK10::vkCmdBindPipeline($commandBuffer, $pipelineBindPoint instanceof \BackedEnum ? $pipelineBindPoint->value : $pipelineBindPoint, $pipeline);
}
```

## What this layer adds

Exactly three things, and nothing else:

1. **Types.** `int` → `VkResult|int`, `double` → `float`, `var` → `?string`.
   Every parameter keeps its position and its meaning.
2. **Enums.** The constants `ext-vulkan` deliberately does not bind. See
   [enum-mapping.md](/enum-mapping.md).
3. **Struct value objects.** The typed shape of the array the extension
   already takes and returns. See [struct-values.md](/struct-values.md).

## Why an object with state is still a projection

jovian/ogx's tiers are all stateless static classes, and a reader coming from
there will stop at `final readonly class VkApplicationInfo` and ask what it is
doing inside a rule that says "one method, one call".

The answer is that **it holds no state the extension did not already hold for
it.** `VkApplicationInfo::pack()` in the extension takes an array of seven
members; the value object *is* that array with names and types on it. So:

- `toArray()` is the object flattened and `fromArray()` is its inverse. Both
  make **zero** extension calls, which the parity gate checks explicitly —
  a `toArray()` that reached for the extension would be composition wearing a
  value object's clothes.
- `pack()`, `packInto()`, `unpack()` and `size()` make **exactly one** each.
- Nothing is fetched, computed, cached or defaulted beyond the `sType` the
  registry itself names. No destructor, no lifetime, no pointer ownership.

```php
public function pack(): int
{
    return ExtVkApplicationInfo::pack($this->toArray());
}
```

`pack()` and `packInto()` are instance methods because the object already
carries its members; handing them back to it would make it a wrapper rather
than a value. `unpack()` and `size()` are static because neither needs one.

## The three places a value is touched

All three are narrowing or re-typing, never changing a value:

- **`?string`.** The extension's `var` return is a PHP string or `null`; the
  projection declares `?string` and narrows with `is_string()`. Exactly one
  annotation in the whole extension returns `var` — `Bridge::read` — and
  `verify-return-typing.mjs` fails if that ever stops being true.
- **`ApiVersion`.** `Bridge::version()`'s packed `uint32` becomes a readonly
  value object. `fromPacked()` is arithmetic on a number the one call already
  returned, not a second call.
- **`Structs\*`.** `unpack()` returns the value object the extension's array
  describes. Declared `: self`, so there is no second shape to handle.

## What this layer must never add

- **No convenience composition.** No `createInstance($name, $extensions)` that
  allocates the C strings, builds the arrays, packs three structs and reads
  the handle back. That is five extension calls and a policy; it is `venusian`.
- **No lifetime ownership.** See [runtime.md](/runtime.md) on why there is no
  `Buffer` object, and why a `Structs\*` is not a counter-example.
- **No `sType` filling beyond the registry's own `values=`.** The extension
  fills none at all; this layer defaults the 296 the registry says have exactly
  one legal value, and leaves `VkBaseInStructure` and `VkBaseOutStructure` at
  `0` because the registry gives them none.
- **No platform choice.** Every WSI class exists on both boxes and this package
  chooses neither. See [platforms.md](/platforms.md).
- **No error side channel.** A refused call warns and returns `0`/void. There
  are no exceptions in this package.

## The one thing that throws

`Structs\*::unpack(0)` does. The extension warns `ptr is NULL` and returns
`null`, the projection hands that straight to `fromArray(array $members)`, and
PHP raises a `TypeError`.

That is deliberate and it is the honest shape. `unpack()` is declared `: self`
and **there is no struct at address 0**. Casting the null to `[]` would hand
back a zero-filled object the caller never had, and inventing a value is the
one thing this layer must never do. The extension's own warning already names
the struct, so the diagnosis is not lost.
`tests/Structs/StructValueTest.php` pins it.

## Parameter names are the registry's, at both layers

Zephir reserves some identifiers and `ext-vulkan`'s `gen-zep.php` escapes any
method or parameter name that hits the list with a trailing underscore. ogx
needs a whole rule about this, because `glShaderSource(…, $string_, …)` really
is what that extension publishes.

Here the escape never fires. **Measured against the committed tree: zero
method names and zero parameter names in all 1751 annotations collide with a
Zephir reserved word or are all-caps**, so nothing is escaped and there is no
spelling to restore. The projection takes the annotation's name as given, and
it is the registry's own name on both sides — named arguments included.

That is a fact about today's `vk.xml`, not a guarantee. `gen-zep.php` still
applies the rule, so a re-vendored registry that introduced a parameter called
`object` would make the two layers disagree about that one name, and this
paragraph would need re-measuring rather than re-reading.
