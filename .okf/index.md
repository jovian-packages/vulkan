---
okf_version: "0.2"
---

# jovian/vulkan — knowledge bundle

Vulkan typed in PHP: `ext-vulkan` 0.8.0 projected 1:1 into typed classes, plus
the enums the extension deliberately withholds and one readonly value object
per registry struct. Read this index first, then open only the concepts the
task needs.

- [projection-rule.md](/projection-rule.md) — the rule that bounds this
  package: one method = one extension call. What the struct tier is allowed to
  be under it, and where composition goes instead.
- [enum-mapping.md](/enum-mapping.md) — **the design work.** `vk.xml` is the
  one registry: it says what every name is *and* what every value is worth, so
  ogx's two-file rule collapses to one. The whole mapping is stated as rules a
  machine follows rather than judgements a person made.
- [struct-values.md](/struct-values.md) — **the new concept.** 368 readonly
  value objects, one per registry struct or union: the typed shape of the array
  the extension already takes and returns, and nothing else.
- [generation.md](/generation.md) — the annotation × registry join, the four
  Vulkan adaptations of jovian/ogx's generator, and what each gate does and
  does not prove.
- [runtime.md](/runtime.md) — the hand-written half: the twelve Bridge calls,
  `ApiVersion`, and why there is no `Buffer` object.
- [platforms.md](/platforms.md) — one binding, two boxes: why every WSI class
  exists everywhere, what a command the loader cannot resolve does, and what
  the Pi proves that the Mac cannot.

## Fast facts

| | |
|---|---|
| Version | 0.8.0, PHP `^8.4`, requires `ext-vulkan` `^0.8.0`, macOS **and** Linux |
| Namespace | `Jovian\Bindings\Vulkan\` |
| Generated | 13 command classes / 267 methods, 368 structs / 2296 members, 114 enums / 1224 cases |
| Hand-written | `src/Runtime/Bridge.php` (12 calls), `src/Values/ApiVersion.php` |
| Surface | 267 + 368×4 + 12 = **1751**, the extension's own count, 0 reserved |
| Typed | 38 enum parameters, 94 enum returns, 468 enum-typed members; masks stay `int` |
| Proof | `examples/proof_headless_typed.php` → `PROOF_HEADLESS_TYPED_OK`, Mac and Pi |

## Layering

`ext-vulkan` (1:1 binding + unavoidable glue) → **`jovian/vulkan`** (enums,
typed projection, struct value objects) → `venusian` (composition) → Surface
(cross-platform abstraction). Never reach up the stack, and never build a
cross-platform abstraction here — that is Surface's job.
