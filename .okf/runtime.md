---
type: Component
title: The hand-written half
description: >-
  Runtime\Bridge (the twelve glue calls), Values\ApiVersion, and the Buffer
  object that deliberately does not exist.
resource: src/Runtime
tags: [runtime, bridge, values, memory, vulkan]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Runtime

Two files in `src/` are hand-written. Everything else is generated and must
never be hand-edited, and `scripts/gates/verify-style.mjs` fails if a third
appears.

## `Runtime\Bridge` — twelve calls

`ext-vulkan` keeps every piece of platform knowledge in `Vulkan\Bridge\Bridge`
and nowhere else (its rule 9). This class is that seam, typed, one PHP method
per extension call:

| | |
|---|---|
| `load(): bool` | `dlopen` the platform's loader and take `vkGetInstanceProcAddr` from it |
| `loadInstance(int): bool` | record the instance, resolve `vkGetDeviceProcAddr` through it |
| `loadDevice(int): bool` | record the device for direct dispatch |
| `isAvailable(string): bool` | can the loader, instance or device make this call |
| `procAddress(string): int` | the same resolution, as pointer bits |
| `version(): ApiVersion` | the loader's `vkEnumerateInstanceVersion` answer, typed |
| `alloc(int): int` / `free(int): void` | raw pointer bits, `0` = failure/NULL |
| `write(int, int, string): bool` | bytes into a block |
| `read(int, int, int): ?string` | bytes out of a block, `null` on refusal |
| `cstring(string): int` | a NUL-terminated copy nothing else in PHP can make |
| `readCString(int): string` | one back; `""` for `0` |

Only two of the twelve are touched: `version()` builds a value object out of
the `uint32` the one call already returned, and `read()` narrows the
extension's `var` to `?string`.

### `isAvailable` really is availability here

This is where Vulkan and OpenGL part company, and the difference is worth
knowing if you have read [jovian/ogx's runtime.md](../../ogx/.okf/runtime.md).

ogx needs a version gate because symbol resolution and availability are
different questions there — Mesa's loader returns a live stub for any name you
give it, including names no OpenGL ever had. Vulkan has no such gap:
`vkGetInstanceProcAddr` returns NULL for a command above the instance's API
version *and* for an extension that was not enabled, and
`vkGetDeviceProcAddr` does the same per device. So ext-vulkan has **no version
table and no version comparison**; `src/phpvk-registry.c` carries only the
level each command resolves at, which is a fact about which dispatch table to
ask.

`Bridge::isAvailable('vkCreateInstance')` is therefore literally the
resolution the binding would do, and `tests/Runtime/BridgeTest.php` asserts
both halves: a global command resolves with no instance at all, and a name no
Vulkan ever had resolves to `false`. On the Pi, where ogx's namesake answers
`true` for nonsense, this one answers `false`.

### Two refusals, and they mean different things

```
<name>: call Bridge::load() first
<name>: call Bridge::loadInstance() first
<name> is not available on this loader|instance|device
```

The first two are a missing step in your own code. The third is the driver
saying no. Both warn and return `0`/false; there is no third channel and there
are no exceptions. The first is only observable in a process that has not made
an instance yet, so `BridgeTest` gives that claim its own subprocess rather
than depending on which test ran first.

## `Values\ApiVersion`

Vulkan spells a version as one `uint32` —
`VK_MAKE_API_VERSION(variant, major, minor, patch)` — and hands over that same
number everywhere: `Bridge::version()`, `VkApplicationInfo.apiVersion`,
`VkPhysicalDeviceProperties.apiVersion`. This is that number unpacked, on
`jovian/metal`'s `MTLSize` shape and `jovian/ogx`'s `ContextVersion`
precedent.

```php
ApiVersion::make(1, 3, 0)->toPacked();          // 4206592
ApiVersion::fromPacked($props->apiVersion);      // 1.4.357
$version->atLeast(1, 3);
```

Everything is arithmetic on a number the extension already returned:
`fromPacked()` is a constructor and `toPacked()` is its inverse, exactly the
way `Structs\*::fromArray()` and `toArray()` are for a struct.

`0.0.0` is the honest answer when the loader could not say — a 1.0 loader has
no `vkEnumerateInstanceVersion` and `Bridge::version()` returns `0`. It is not
an error and it does not throw.

`atLeast()` deliberately does **not** compare the variant. A non-zero variant
is a different API with its own numbering, so "newer" has no answer across
variants; read `$version->variant` when you need to know which API you are
holding. `__toString()` prints `1.4.357`, or `1.4.357 (variant 2)` when it is
not the Vulkan everyone means.

## There is no `Buffer` object, on purpose

`alloc`/`write`/`read`/`free`/`cstring` are each exactly one extension call,
so a `Buffer` value object *could* be written inside the projection rule. It is
not, because the moment such an object has a destructor it owns a lifetime —
and deciding when GPU-adjacent memory is released is a **composition**
decision. That belongs to `venusian`, next to the memory pools and the frame
graph, not to a layer whose entire promise is that it adds types and nothing
else.

**The 368 struct value objects are not a counter-example.** A `Structs\*`
holds member *values*, never a pointer's lifetime: `pack()` hands the block
straight back to the caller, and the object has no destructor and no memory of
what it packed. See [struct-values.md](/struct-values.md).

The bytes are built and read with `pack()` and `unpack()`, exactly as
ext-vulkan's own proof does:

```php
$out = Bridge::alloc(8);
VK10::vkCreateInstance($info, 0, $out);
$instance = unpack('P', (string) Bridge::read($out, 0, 8))[1];
Bridge::free($out);
```

`vkMapMemory`'s pointer is the case that matters: it is the driver's memory,
not a block this extension allocated, so `Bridge::write` has no extent to
bounds-check it against and writes it as given. That is the price of the
pointer rule, and it is the same price a C caller pays.
