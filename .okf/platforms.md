---
type: Convention
title: One binding, two boxes
description: >-
  Why every WSI class exists everywhere, what a command the loader cannot
  resolve does, and what the Pi proves that the Mac cannot.
tags: [platform, wsi, moltenvk, mesa, linux, darwin, availability]
status: stable
generated:
  by: claude-fable-5.1
  at: 2026-09-13T00:00:00Z
---

# Platforms

`jovian/metal` is macOS-only. `jovian/vulkan` is not, and that changes what has
to be proved.

## Every class exists on both boxes

`Vulkan\Ext\EXTMetalSurface`, `Vulkan\Ext\KHRWaylandSurface`,
`Vulkan\Ext\KHRXcbSurface` and `Vulkan\Ext\KHRXlibSurface` are declared on
Linux *and* on macOS, because every ext-vulkan entry point is resolved at
runtime and **there is not one `#ifdef` in any of its generated binding
bodies** — the WSI and Metal types are declared by `src/phpvk-platform.h`
itself rather than pulled in from platform headers.

So the projection emits all 13 command classes for both platforms and chooses
neither. Choosing is the caller's job.
`tests/Feature/SmokeTest.php` asserts both classes exist and then calls the
*other* platform's one, from whichever box it is on.

## The unavailable-call contract

**An `E_WARNING`, a `0` or void, and a process that lives.** No exception, no
error side channel, no process-global "last error". That is ext-vulkan's rule
8 and this package does not add to it.

```
Vulkan\Ext\KHRWaylandSurface\KHRWaylandSurface::vkCreateWaylandSurfaceKHR():
vkCreateWaylandSurfaceKHR is not available on this instance
```

## PHP_OS_FAMILY appears three times in this package, and never in src/

Once in `examples/proof_headless_typed.php` and once in `tests/Pest.php`, and
both are the same decision: **MoltenVK is a portability driver, and without
`VK_KHR_portability_enumeration` and the matching instance flag the loader
reports zero physical devices — no error, just an empty list.** That is the
driver's rule, not this package's, and the caller is where it belongs. The
third is in `tests/Feature/SmokeTest.php`, choosing which of the two WSI
classes is the one this box cannot resolve.

All three are **callers choosing**, which is exactly where a platform decision
is allowed to live.

There is no `PHP_OS_FAMILY` anywhere in `src/`, and there must never be one:
`scripts/gates/verify-style.mjs` fails on it. A cross-platform abstraction is
Surface's job, not this layer's.

## What each box proves

| | Mac (MoltenVK on Apple M1 Pro) | Pi 5 (Mesa V3D 7.1.7.0) |
|---|---|---|
| generator | full | reproduces the tree from the Pi's own `vk.xml`, digest-checked |
| node gates | all | none — no `node` on the Pi |
| loader | `libvulkan.1.dylib`, reports 1.4.357 | `libvulkan.so.1` |
| device | `Apple M1 Pro`, api 1.3.357, `INTEGRATED_GPU` | `V3D 7.1.7.0`, api 1.3.354, `INTEGRATED_GPU` |
| portability | `VK_KHR_portability_enumeration` + `VK_KHR_portability_subset` required | neither exists, neither is asked for |
| driver kind | a translation layer over Metal | a real Vulkan driver on real hardware |
| unavailable call | `KHRWaylandSurface::vkCreateWaylandSurfaceKHR` | `EXTMetalSurface::vkCreateMetalSurfaceEXT` |
| proof | `PROOF_HEADLESS_TYPED_OK`, centre `255,128,64,255` | `PROOF_HEADLESS_TYPED_OK`, centre `255,128,64,255` |

The last row is the whole point: **the same bytes on both boxes, and the same
bytes ext-vulkan's own untyped proof renders.** Until the Pi runs, "adding
types did not change a byte" is a claim about one translation layer.

Both proofs prefer an `INTEGRATED_GPU`, and on the Pi that is not cosmetic:
V3D and llvmpipe both offer a graphics queue and llvmpipe is usually
enumerated second, so taking the first match would be a coin flip on
enumeration order. The typed proof makes the same choice the extension's proof
makes, and `verify-smoke.mjs` fails if the two ever pick different devices.

## The Pi procedure

```bash
bash scripts/pi-verify.sh        # PI_VERIFY_OK
```

Tar over `fnk`'s stdin to `/home/angel/jovian-vulkan`, `composer install`
there, regenerate, digest-compare, `vendor/bin/pest`, the struct round trip,
reflection, the typed proof. `vendor/` never travels and `.git` never travels.
A Pest run that *skips* is treated as a failure, because a skip is not a pass.
**Fix on the Mac, re-push — never edit on the Pi.** Overrides:
`PI_JOVIAN_VULKAN_DIR`, `PI_VULKAN_DIR`, `PI_PHP_BIN`.
