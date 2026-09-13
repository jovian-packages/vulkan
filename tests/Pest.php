<?php

declare(strict_types=1);

/*
| Pest bootstrap for jovian/vulkan.
|
| Extension-dependent suites skip when ext-vulkan is absent. A skip is not a
| pass, and scripts/gates/verify-platform-control.mjs is what catches a suite
| that skipped green while the extension was in fact loaded.
|
| This package runs on two boxes and has no opinion about either. The one
| platform branch in the whole test suite is vulkanInstance()'s portability
| flag, for the same reason examples/proof_headless_typed.php has one: MoltenVK
| reports zero physical devices without VK_KHR_portability_enumeration, which is
| the driver's rule, not this package's. The caller chooses, and here the test
| is the caller.
*/

use Jovian\Bindings\Vulkan\Enums\VkInstanceCreateFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkPhysicalDeviceType;
use Jovian\Bindings\Vulkan\Enums\VkQueueFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkResult;
use Jovian\Bindings\Vulkan\Runtime\Bridge;
use Jovian\Bindings\Vulkan\Structs\VkApplicationInfo;
use Jovian\Bindings\Vulkan\Structs\VkInstanceCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPhysicalDeviceProperties;
use Jovian\Bindings\Vulkan\Structs\VkQueueFamilyProperties;
use Jovian\Bindings\Vulkan\Values\ApiVersion;
use Jovian\Bindings\Vulkan\VK\VK10;

function vulkanExtensionLoaded(): bool
{
    return extension_loaded('vulkan');
}

function vulkanRequireExtension(): void
{
    if (! vulkanExtensionLoaded()) {
        test()->skip('ext-vulkan is not loaded');
    }
}

/**
 * The ext-vulkan checkout: the Mac sibling, the Pi checkout, or
 * JOVIAN_VULKAN_EXT. Its `src/*.h` is the annotated surface the parity tests
 * measure against, and its `scripts/khronos/vk.xml` is the one registry.
 */
function vulkanExtRoot(): string
{
    $root = dirname(__DIR__);
    foreach ([
        getenv('JOVIAN_VULKAN_EXT') ?: '',
        $root . '/../../php-io-extensions/vulkan/vulkan',
        $root . '/../php-io-extensions/vulkan/vulkan',
        '/home/angel/vulkan',
    ] as $candidate) {
        if ($candidate !== '' && is_dir($candidate . '/src') && is_file($candidate . '/scripts/khronos/vk.xml')) {
            return $candidate;
        }
    }

    test()->skip('the ext-vulkan checkout is not reachable; set JOVIAN_VULKAN_EXT');
}

/**
 * A live VkInstance, created once per process, with the loader pointed at it.
 *
 * @return array{instance: int, version: ApiVersion}
 */
function vulkanInstance(): array
{
    static $instance = null;
    static $failed = null;

    vulkanRequireExtension();

    if (! is_null($failed)) {
        test()->skip($failed);
    }
    if (! is_null($instance)) {
        return $instance;
    }

    if (! Bridge::load()) {
        $failed = 'Bridge::load() could not open a Vulkan loader';
        test()->skip($failed);
    }

    /*
     * MoltenVK is a portability driver: without VK_KHR_portability_enumeration
     * and the matching instance flag the loader reports zero physical devices —
     * no error, just an empty list. The driver's rule, not this package's.
     */
    $darwin = PHP_OS_FAMILY === 'Darwin';
    $names = $darwin ? ['VK_KHR_portability_enumeration'] : [];
    $pointers = array_map(static fn (string $name): int => Bridge::cstring($name), $names);
    $array = Bridge::alloc(max(1, count($pointers)) * 8);
    foreach ($pointers as $i => $pointer) {
        Bridge::write($array, $i * 8, pack('P', $pointer));
    }

    $applicationInfo = (new VkApplicationInfo(
        pApplicationName: Bridge::cstring('jovian-vulkan-tests'),
        applicationVersion: 1,
        apiVersion: ApiVersion::make(1, 1, 0)->toPacked(),
    ))->pack();

    $createInfo = (new VkInstanceCreateInfo(
        flags: $darwin ? VkInstanceCreateFlagBits::ENUMERATE_PORTABILITY_BIT_KHR->value : 0,
        pApplicationInfo: $applicationInfo,
        enabledExtensionCount: count($pointers),
        ppEnabledExtensionNames: $array,
    ))->pack();

    $out = Bridge::alloc(8);
    $result = VK10::vkCreateInstance($createInfo, 0, $out);
    if ($result !== VkResult::SUCCESS->value) {
        $failed = 'vkCreateInstance returned VkResult ' . $result;
        test()->skip($failed);
    }
    $handle = vulkanHandleAt($out);
    if ($handle === 0 || ! Bridge::loadInstance($handle)) {
        $failed = 'vkCreateInstance wrote a null handle, or Bridge::loadInstance refused it';
        test()->skip($failed);
    }

    $instance = ['instance' => $handle, 'version' => Bridge::version()];

    return $instance;
}

/**
 * The first physical device with a graphics queue family, preferring a real
 * GPU — the same choice examples/proof_headless_typed.php makes, and for the
 * same reason: on the Pi both V3D and llvmpipe offer one.
 *
 * @return array{handle: int, properties: VkPhysicalDeviceProperties, queueFamily: int}
 */
function vulkanPhysicalDevice(): array
{
    static $device = null;
    static $failed = null;

    $context = vulkanInstance();
    if (! is_null($failed)) {
        test()->skip($failed);
    }
    if (! is_null($device)) {
        return $device;
    }

    $countOut = Bridge::alloc(4);
    VK10::vkEnumeratePhysicalDevices($context['instance'], $countOut, 0);
    $count = vulkanIntAt($countOut);
    if ($count === 0) {
        $failed = 'the loader reports no physical device';
        test()->skip($failed);
    }

    $list = Bridge::alloc($count * 8);
    VK10::vkEnumeratePhysicalDevices($context['instance'], $countOut, $list);

    $propertiesBlock = Bridge::alloc(VkPhysicalDeviceProperties::size());
    $chosen = null;
    $preferred = -1;

    for ($i = 0; $i < $count; $i++) {
        $candidate = vulkanHandleAt($list, $i);

        VK10::vkGetPhysicalDeviceQueueFamilyProperties($candidate, $countOut, 0);
        $families = vulkanIntAt($countOut);
        if ($families === 0) {
            continue;
        }
        $familyBlock = Bridge::alloc($families * VkQueueFamilyProperties::size());
        VK10::vkGetPhysicalDeviceQueueFamilyProperties($candidate, $countOut, $familyBlock);

        $family = -1;
        for ($f = 0; $f < $families; $f++) {
            $properties = VkQueueFamilyProperties::unpack($familyBlock + $f * VkQueueFamilyProperties::size());
            if (($properties->queueFlags & VkQueueFlagBits::GRAPHICS_BIT->value) !== 0 && $properties->queueCount > 0) {
                $family = $f;
                break;
            }
        }
        Bridge::free($familyBlock);
        if ($family < 0) {
            continue;
        }

        VK10::vkGetPhysicalDeviceProperties($candidate, $propertiesBlock);
        $properties = VkPhysicalDeviceProperties::unpack($propertiesBlock);
        $integrated = VkPhysicalDeviceType::INTEGRATED_GPU->value;
        if (is_null($chosen) || ($properties->deviceType === $integrated && $preferred !== $integrated)) {
            $chosen = ['handle' => $candidate, 'properties' => $properties, 'queueFamily' => $family];
            $preferred = $properties->deviceType;
        }
    }

    Bridge::free($propertiesBlock);
    Bridge::free($list);
    Bridge::free($countOut);

    if (is_null($chosen)) {
        $failed = 'no physical device has a graphics queue family';
        test()->skip($failed);
    }
    $device = $chosen;

    return $device;
}

function vulkanHandleAt(int $ptr, int $index = 0): int
{
    return unpack('P', (string) Bridge::read($ptr, $index * 8, 8))[1];
}

function vulkanIntAt(int $ptr, int $offset = 0): int
{
    return unpack('V', (string) Bridge::read($ptr, $offset, 4))[1];
}

/**
 * Run something the loader cannot do, and hand back the E_WARNING it raised.
 * ext-vulkan has no error side channel: a refused call warns and returns
 * 0/void, and that is the whole contract.
 *
 * @return array{result: mixed, warning: ?string}
 */
function vulkanCaptureWarning(callable $call): array
{
    $warning = null;
    set_error_handler(static function (int $number, string $message) use (&$warning): bool {
        $warning = $message;

        return true;
    });

    try {
        $result = $call();
    } finally {
        restore_error_handler();
    }

    return ['result' => $result, 'warning' => $warning];
}
