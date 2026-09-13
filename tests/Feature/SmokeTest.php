<?php

declare(strict_types=1);

use Jovian\Bindings\Vulkan\Ext\EXTMetalSurface;
use Jovian\Bindings\Vulkan\Ext\KHRWaylandSurface;
use Jovian\Bindings\Vulkan\Enums\VkPhysicalDeviceType;
use Jovian\Bindings\Vulkan\Enums\VkResult;
use Jovian\Bindings\Vulkan\Runtime\Bridge;
use Jovian\Bindings\Vulkan\Structs\VkExtensionProperties;
use Jovian\Bindings\Vulkan\Structs\VkPhysicalDeviceProperties;
use Jovian\Bindings\Vulkan\Values\ApiVersion;
use Jovian\Bindings\Vulkan\VK\VK10;

/*
| Live tests, on a real Vulkan instance and a real physical device.
| vulkanInstance() is the only thing in this package that knows which box it is
| on, and only because MoltenVK needs the portability flag to report a device
| at all.
*/

it('creates an instance through typed value objects', function (): void {
    $context = vulkanInstance();

    expect($context['instance'])->not->toBe(0);
    expect($context['version'])->toBeInstanceOf(ApiVersion::class);
    expect($context['version']->atLeast(1))->toBeTrue();
});

it('finds a physical device with a graphics queue family', function (): void {
    $device = vulkanPhysicalDevice();

    expect($device['handle'])->not->toBe(0);
    expect($device['queueFamily'])->toBeGreaterThanOrEqual(0);

    $properties = $device['properties'];
    expect($properties)->toBeInstanceOf(VkPhysicalDeviceProperties::class);
    expect($properties->deviceName)->toBeString()->not->toBeEmpty();

    // deviceType is declared VkPhysicalDeviceType in the registry and crosses
    // as an int, so the caller converts. This is the idiom.
    expect(VkPhysicalDeviceType::tryFrom($properties->deviceType))->not->toBeNull();

    // apiVersion is the packed uint32 every Vulkan version is.
    $version = ApiVersion::fromPacked($properties->apiVersion);
    expect($version->atLeast(1))->toBeTrue("{$properties->deviceName} reports {$version}");
});

it('reads a char[N] member back as a string, cut at the first NUL', function (): void {
    $device = vulkanPhysicalDevice();

    $countOut = Bridge::alloc(4);
    $result = VK10::vkEnumerateDeviceExtensionProperties($device['handle'], '', $countOut, 0);
    expect($result)->toBe(VkResult::SUCCESS->value);

    $count = vulkanIntAt($countOut);
    expect($count)->toBeGreaterThan(0);

    $list = Bridge::alloc($count * VkExtensionProperties::size());
    VK10::vkEnumerateDeviceExtensionProperties($device['handle'], '', $countOut, $list);

    $names = [];
    for ($i = 0; $i < $count; $i++) {
        $properties = VkExtensionProperties::unpack($list + $i * VkExtensionProperties::size());
        expect($properties->extensionName)->toBeString();
        $names[] = $properties->extensionName;
    }

    expect($names)->toContain('VK_KHR_swapchain');
    foreach ($names as $name) {
        expect(strlen($name))->toBeLessThan(256);
        expect(str_contains($name, "\0"))->toBeFalse();
    }

    Bridge::free($list);
    Bridge::free($countOut);
});

it('hands back a VkResult as an int, and the enum is how you read it', function (): void {
    vulkanInstance();

    /*
     * `VkResult|int` RETURNS AN INT. `$r === VkResult::SUCCESS` is always
     * false; `VkResult::tryFrom($r)` is the idiom. Converting inside the
     * projection would be behaviour, and this layer adds none.
     */
    $countOut = Bridge::alloc(4);
    $result = VK10::vkEnumerateInstanceExtensionProperties('', $countOut, 0);

    expect($result)->toBeInt();
    expect($result)->toBe(VkResult::SUCCESS->value);
    expect(VkResult::tryFrom($result))->toBe(VkResult::SUCCESS);
    expect($result === VkResult::SUCCESS)->toBeFalse();

    Bridge::free($countOut);
});

it('warns and returns 0 for a command this platform cannot resolve', function (): void {
    vulkanInstance();

    /*
     * The other platform's WSI class. Every entry point is resolved at
     * runtime and there is not one #ifdef in the extension's generated C, so
     * both classes exist on both boxes and simply never resolve on the wrong
     * one. The contract is an E_WARNING and a 0, with no exception and no
     * error side channel.
     */
    expect(class_exists(EXTMetalSurface::class))->toBeTrue();
    expect(class_exists(KHRWaylandSurface::class))->toBeTrue();

    $captured = PHP_OS_FAMILY === 'Darwin'
        ? vulkanCaptureWarning(static fn (): int => KHRWaylandSurface::vkCreateWaylandSurfaceKHR(0, 0, 0, 0))
        : vulkanCaptureWarning(static fn (): int => EXTMetalSurface::vkCreateMetalSurfaceEXT(0, 0, 0, 0));

    expect($captured['warning'])->toBeString();
    expect($captured['warning'])->toContain('is not available');
    expect($captured['result'])->toBe(0);
});
