<?php

declare(strict_types=1);

use Jovian\Bindings\Vulkan\Enums\VkFormat;
use Jovian\Bindings\Vulkan\Enums\VkImageType;
use Jovian\Bindings\Vulkan\Enums\VkStructureType;
use Jovian\Bindings\Vulkan\Runtime\Bridge;
use Jovian\Bindings\Vulkan\Structs\VkApplicationInfo;
use Jovian\Bindings\Vulkan\Structs\VkClearColorValue;
use Jovian\Bindings\Vulkan\Structs\VkClearValue;
use Jovian\Bindings\Vulkan\Structs\VkExtent3D;
use Jovian\Bindings\Vulkan\Structs\VkImageCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkViewport;
use Jovian\Bindings\Vulkan\Values\ApiVersion;

/*
| The fourth tier. A value object is the typed shape of the array the extension
| already takes and returns — nothing more. toArray() is this object flattened,
| fromArray() is its inverse, and the four bound methods are one extension call
| each.
*/

it('shapes a struct as its registry members, in order, with the registry types', function (): void {
    $parameters = (new ReflectionClass(VkApplicationInfo::class))->getConstructor()->getParameters();

    expect(array_map(static fn (ReflectionParameter $p): string => $p->getName(), $parameters))->toBe([
        'sType', 'pNext', 'pApplicationName', 'applicationVersion',
        'pEngineName', 'engineVersion', 'apiVersion',
    ]);

    // sType defaults to the one constant vk.xml's `values=` names for it.
    expect((new VkApplicationInfo())->sType)->toBe(VkStructureType::APPLICATION_INFO);

    // Every pointer, pNext included, is bits in an int and is never followed.
    expect((string) $parameters[1]->getType())->toBe('int');
    expect((string) $parameters[2]->getType())->toBe('int');

    // readonly, so a value object cannot be edited into a different struct.
    expect((new ReflectionClass(VkApplicationInfo::class))->isReadOnly())->toBeTrue();
});

it('flattens to exactly the array the extension takes, enums as ints', function (): void {
    $info = new VkApplicationInfo(
        pApplicationName: 0x1234,
        applicationVersion: 7,
        apiVersion: ApiVersion::make(1, 3, 0)->toPacked(),
    );

    expect($info->toArray())->toBe([
        'sType' => 0,
        'pNext' => 0,
        'pApplicationName' => 0x1234,
        'applicationVersion' => 7,
        'pEngineName' => 0,
        'engineVersion' => 0,
        'apiVersion' => (1 << 22) | (3 << 12),
    ]);

    // An enum case and its int are the same call, because toArray() unwraps.
    expect((new VkImageCreateInfo(imageType: VkImageType::TYPE_2D, format: VkFormat::R8G8B8A8_UNORM))->toArray())
        ->toBe((new VkImageCreateInfo(imageType: 1, format: 37))->toArray());
});

it('nests a by-value struct and treats an absent one as zero', function (): void {
    $with = new VkImageCreateInfo(extent: new VkExtent3D(width: 64, height: 64, depth: 1));
    expect($with->toArray()['extent'])->toBe(['width' => 64, 'height' => 64, 'depth' => 1]);

    /*
     * A promoted readonly property cannot default to `new VkExtent3D()` — PHP
     * allows `new` in a parameter default but not in a property default, and a
     * promoted parameter is both. So the default is null and toArray() leaves
     * the key out, which the extension zero-fills: `memset` runs before every
     * fill, so a missing key IS zero rather than a default this layer invented.
     */
    expect(array_key_exists('extent', (new VkImageCreateInfo())->toArray()))->toBeFalse();
});

it('reads a union as every member and writes only what it was given', function (): void {
    $reflection = new ReflectionClass(VkClearColorValue::class);
    foreach ($reflection->getConstructor()->getParameters() as $parameter) {
        expect($parameter->getType()->allowsNull())->toBeTrue($parameter->getName());
        expect($parameter->getDefaultValue())->toBeNull($parameter->getName());
    }

    $value = new VkClearValue(color: new VkClearColorValue(float32: [0.0, 0.0, 0.0, 1.0]));
    expect($value->toArray())->toBe(['color' => ['float32' => [0.0, 0.0, 0.0, 1.0]]]);
    expect(array_key_exists('depthStencil', $value->toArray()))->toBeFalse();
});

it('round-trips through the extension and changes nothing', function (): void {
    vulkanRequireExtension();
    Bridge::load();

    $viewport = new VkViewport(x: 0.0, y: 0.0, width: 64.0, height: 64.0, minDepth: 0.0, maxDepth: 1.0);
    $ptr = $viewport->pack();
    expect($ptr)->not->toBe(0);

    // The extension's own unpack is the array of record; fromArray()->toArray()
    // must be identical to it, keys, order and types included.
    $raw = Vulkan\Struct\VkViewport\VkViewport::unpack($ptr);
    expect(VkViewport::fromArray($raw)->toArray())->toBe($raw);
    expect(VkViewport::unpack($ptr))->toEqual($viewport);

    // packInto() writes exactly the bytes pack() would have written, which is
    // what makes an array of value objects stride correctly.
    $block = Bridge::alloc(VkViewport::size());
    $viewport->packInto($block);
    expect(Bridge::read($block, 0, VkViewport::size()))->toBe(Bridge::read($ptr, 0, VkViewport::size()));

    Bridge::free($block);
    Bridge::free($ptr);
});

it('strides an array of value objects on size()', function (): void {
    vulkanRequireExtension();
    Bridge::load();

    $size = VkViewport::size();
    expect($size)->toBeGreaterThan(0);

    $block = Bridge::alloc(2 * $size);
    (new VkViewport(width: 1.0))->packInto($block);
    (new VkViewport(width: 2.0))->packInto($block + $size);

    expect(VkViewport::unpack($block)->width)->toBe(1.0);
    expect(VkViewport::unpack($block + $size)->width)->toBe(2.0);

    Bridge::free($block);
});

it('does not invent a struct at address zero', function (): void {
    vulkanRequireExtension();
    Bridge::load();

    /*
     * The extension warns "ptr is NULL" and returns null; the projection is
     * one call and hands that to fromArray(), which is typed `array`, so PHP
     * raises a TypeError. That is deliberate: unpack() is declared `: self`,
     * and there is no struct at address 0. Casting the null to `[]` instead
     * would hand back a zero-filled object the caller never had — inventing a
     * value is the one thing this layer must never do.
     */
    $captured = vulkanCaptureWarning(static function (): mixed {
        try {
            return VkApplicationInfo::unpack(0);
        } catch (TypeError $e) {
            return $e;
        }
    });

    expect($captured['result'])->toBeInstanceOf(TypeError::class);
    expect($captured['warning'])->toContain('ptr is NULL');
});
