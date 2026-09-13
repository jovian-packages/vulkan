# jovian/vulkan

Vulkan, typed in PHP.

[`ext-vulkan`](https://github.com/php-io-extensions/vulkan) binds Vulkan 1.0 ..
1.4 and eight WSI, Metal-interop and debug extensions 1:1 into PHP, and
deliberately binds **no constants at all** and gives its 368 structs a flat
`pack(array $members)` tier. This package is the layer that adds what the
extension withheld, and nothing else:

- **267 typed command methods** across 13 classes,
- **114 PHP enums** carrying 1224 cases, mined from the Khronos registry,
- **368 readonly struct value objects**, one per registry struct or union,
- **12 typed Bridge calls** and an `ApiVersion` value object.

267 + 368×4 + 12 = **1751**, which is the extension's own surface, exactly.

```bash
composer require jovian/vulkan
```

Requires PHP 8.4+ and `ext-vulkan` 0.8.0. macOS and Linux.

## What it looks like

The extension's own headless-triangle proof opens with 74 inline
`const VK_* = …; // vk.xml` literals and packs its structs out of untyped
arrays, *because this package did not exist yet*:

```php
const VK_STRUCTURE_TYPE_IMAGE_CREATE_INFO = 14;
const VK_IMAGE_TYPE_2D = 1;
const VK_FORMAT_R8G8B8A8_UNORM = 37;
// … 71 more

$imageInfo = VkImageCreateInfo::pack([
    'sType' => VK_STRUCTURE_TYPE_IMAGE_CREATE_INFO,
    'imageType' => VK_IMAGE_TYPE_2D,
    'format' => VK_FORMAT_R8G8B8A8_UNORM,
    'extent' => ['width' => 64, 'height' => 64, 'depth' => 1],
    'samples' => VK_SAMPLE_COUNT_1_BIT,
]);
```

Here:

```php
use Jovian\Bindings\Vulkan\Enums\VkFormat;
use Jovian\Bindings\Vulkan\Enums\VkImageType;
use Jovian\Bindings\Vulkan\Enums\VkSampleCountFlagBits;
use Jovian\Bindings\Vulkan\Structs\VkExtent3D;
use Jovian\Bindings\Vulkan\Structs\VkImageCreateInfo;

$imageInfo = (new VkImageCreateInfo(
    imageType: VkImageType::TYPE_2D,
    format: VkFormat::R8G8B8A8_UNORM,
    extent: new VkExtent3D(width: 64, height: 64, depth: 1),
    samples: VkSampleCountFlagBits::COUNT_1_BIT,
))->pack();
```

`sType` defaults to the one constant `vk.xml` says that member may carry —
296 of the 368 structs have one — and every other member defaults to the zero
the extension would have `memset` there anyway.

`examples/proof_headless_typed.php` is the extension's proof with exactly
those two changes made throughout, and it renders the same two pixels on both
boxes.

## Projection, not composition

**A method here is exactly one extension call, with the same arguments in the
same order.**

```php
public static function vkCmdBindPipeline(int $commandBuffer, VkPipelineBindPoint|int $pipelineBindPoint, int $pipeline): void
{
    ExtVK10::vkCmdBindPipeline($commandBuffer, $pipelineBindPoint instanceof \BackedEnum ? $pipelineBindPoint->value : $pipelineBindPoint, $pipeline);
}
```

If it cannot be written as one extension call, it belongs one layer up in
`venusian`. No `createInstance($name, $extensions)` that allocates the C
strings, builds the arrays and reads the handle back; no object that owns when
memory is freed.

The struct value objects are inside that rule, not an exception to it: they
hold the members the extension's array already held, `toArray()`/`fromArray()`
make **zero** extension calls, and `pack()`/`packInto()`/`unpack()`/`size()`
make exactly one each. `scripts/gates/verify-parity.mjs` checks both halves on
all 2475 methods.

## Three things you will trip on

**An enum-typed return hands back an `int`.** `VkResult|int` is documentation;
converting would be behaviour, and this layer adds none.

```php
$result = VK10::vkCreateInstance($info, 0, $out);   // int
VkResult::tryFrom($result);                          // VkResult|null  ← the idiom
$result === VkResult::SUCCESS;                       // always false   ← the trap
```

**A `VkFlags` mask stays `int` on both sides**, because
`Bits::A | Bits::B` is a `TypeError` in PHP. The `FlagBits` enum is still
emitted; you write `->value | ->value`:

```php
usage: VkImageUsageFlagBits::COLOR_ATTACHMENT_BIT->value
     | VkImageUsageFlagBits::TRANSFER_SRC_BIT->value,
```

A slot the registry declares as the bits type itself carries **one** bit, not
a mask, and is typed — `samples: VkSampleCountFlagBits::COUNT_1_BIT` above.

**`Structs\*::unpack(0)` throws.** The extension warns `ptr is NULL`;
`unpack()` is declared `: self`, and there is no struct at address 0. Handing
back a zero-filled object the caller never had would be inventing a value.

## Verification

```bash
export HERD_PHP_84_INI_SCAN_DIR=$(zsh -ic 'echo $HERD_PHP_84_INI_SCAN_DIR')   # Mac

php scripts/generate.php --check          # commands=267 structs=368 … GEN_OK
node scripts/gates/verify-parity.mjs      # PARITY_OK   1751 = 1751
node scripts/gates/verify-enum-typing.mjs # ENUM_TYPING_OK
node scripts/gates/verify-structs.mjs     # STRUCTS_OK  368 round-tripped live
node scripts/gates/verify-smoke.mjs       # SMOKE_OK    byte-identical to the ext's proof
vendor/bin/pest                           # 41 passed
php examples/proof_headless_typed.php     # PROOF_HEADLESS_TYPED_OK
bash scripts/pi-verify.sh                 # PI_VERIFY_OK
```

Every gate has a positive control that has been watched failing, and the enum
and struct gates re-derive everything from `vk.xml` with an independent parser
rather than reading the generator's own sidecars.

Measured on both boxes, 2026-09-13:

```
Mac   Apple M1 Pro (MoltenVK)   api 1.3.357   pixel(32,32): 255,128,64,255   pixel(0,0): 0,0,0,255
Pi 5  V3D 7.1.7.0 (Mesa)        api 1.3.354   pixel(32,32): 255,128,64,255   pixel(0,0): 0,0,0,255
```

The same bytes `ext-vulkan`'s own untyped proof renders. Adding types did not
change one.

## Layering

`ext-vulkan` (1:1 binding + unavoidable glue) → **`jovian/vulkan`** (enums,
typed projection, struct value objects) → `venusian` (composition) → Surface
(cross-platform abstraction).

This package opens no window, enables no extension, chooses no physical device
and decides nothing. The proof is the caller, and so are you.

## Documentation

Agents and contributors: read [`AGENTS.md`](AGENTS.md) and the OKF bundle in
[`.okf/`](.okf/) — `enum-mapping.md` and `struct-values.md` are where the
design work is.

## Licence

MIT. See [LICENSE](LICENSE).
