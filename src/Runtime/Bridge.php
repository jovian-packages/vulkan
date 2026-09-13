<?php

declare(strict_types=1);

namespace Jovian\Bindings\Vulkan\Runtime;

use Jovian\Bindings\Vulkan\Values\ApiVersion;
use Vulkan\Bridge\Bridge as ExtBridge;

/**
 * The twelve Bridge calls, projected by hand rather than generated — one PHP
 * method per extension call, same arguments, same order.
 *
 * `ext-vulkan` keeps every piece of platform knowledge in `Vulkan\Bridge\Bridge`
 * and nowhere else (its rule 9). This class is that seam, typed: it turns the
 * packed `uint32` `version()` already returned into an `ApiVersion` and narrows
 * `read()`'s `var` to `?string`, and it adds nothing whatsoever to the other
 * ten.
 *
 * There is deliberately **no `Buffer` value object**. `alloc`/`free`/`read`/
 * `write`/`cstring` are raw pointer bits by design (ext-vulkan's pointer rule),
 * and wrapping them in an object that owned its lifetime would be composition —
 * a decision about when memory is released — which is `venusian`'s job, not
 * this layer's. The struct value objects are not a counter-example: a
 * `Structs\*` holds member values, never a pointer's lifetime, and its `pack()`
 * hands the block straight back to the caller to free.
 */
final class Bridge
{
    /** `dlopen` the platform's loader and take `vkGetInstanceProcAddr` from it. */
    public static function load(): bool
    {
        return ExtBridge::load();
    }

    /** Record the instance and resolve `vkGetDeviceProcAddr` through it. */
    public static function loadInstance(int $instance): bool
    {
        return ExtBridge::loadInstance($instance);
    }

    /** Record the device for direct dispatch. Refuses without an instance. */
    public static function loadDevice(int $device): bool
    {
        return ExtBridge::loadDevice($device);
    }

    /**
     * Whether the loader, instance or device can make this call.
     *
     * Unlike OpenGL there is no gap between resolution and availability here:
     * `vkGetInstanceProcAddr` returns NULL for a command above the instance's
     * API version and for an extension that was not enabled, so this is
     * literally the resolution the binding would do.
     */
    public static function isAvailable(string $name): bool
    {
        return ExtBridge::isAvailable($name);
    }

    public static function procAddress(string $name): int
    {
        return ExtBridge::procAddress($name);
    }

    /** The loader's `vkEnumerateInstanceVersion` answer. `0.0.0` on a 1.0 loader. */
    public static function version(): ApiVersion
    {
        return ApiVersion::fromPacked(ExtBridge::version());
    }

    public static function alloc(int $size): int
    {
        return ExtBridge::alloc($size);
    }

    public static function free(int $ptr): void
    {
        ExtBridge::free($ptr);
    }

    public static function write(int $ptr, int $offset, string $bytes): bool
    {
        return ExtBridge::write($ptr, $offset, $bytes);
    }

    public static function read(int $ptr, int $offset, int $length): ?string
    {
        $bytes = ExtBridge::read($ptr, $offset, $length);

        return is_string($bytes) ? $bytes : null;
    }

    public static function cstring(string $text): int
    {
        return ExtBridge::cstring($text);
    }

    public static function readCString(int $ptr): string
    {
        return ExtBridge::readCString($ptr);
    }
}
