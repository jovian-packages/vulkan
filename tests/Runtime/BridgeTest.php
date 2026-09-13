<?php

declare(strict_types=1);

use Jovian\Bindings\Vulkan\Runtime\Bridge;
use Jovian\Bindings\Vulkan\Values\ApiVersion;

/*
| The twelve glue calls, and the one value object the runtime adds. Everything
| here needs the extension; nothing here needs a device.
*/

beforeEach(function (): void {
    vulkanRequireExtension();
});

it('opens a Vulkan loader', function (): void {
    expect(Bridge::load())->toBeTrue();
});

it('types version() as a value object', function (): void {
    Bridge::load();
    $version = Bridge::version();

    expect($version)->toBeInstanceOf(ApiVersion::class);
    expect($version->major)->toBeInt();
    expect($version->minor)->toBeInt();
    expect($version->patch)->toBeInt();
    expect($version->variant)->toBe(0);

    // Every loader this package supports is at least 1.0; 0.0.0 is what a
    // loader with no vkEnumerateInstanceVersion answers, and it is honest
    // rather than an error.
    expect($version->atLeast(1))->toBeTrue("the loader reports {$version}");
    expect((string) $version)->toBe($version->major . '.' . $version->minor . '.' . $version->patch);
});

it('packs and unpacks a version the way VK_MAKE_API_VERSION does', function (): void {
    $version = ApiVersion::make(1, 3, 0);

    // variant << 29 | major << 22 | minor << 12 | patch
    expect($version->toPacked())->toBe((1 << 22) | (3 << 12));
    expect(ApiVersion::fromPacked($version->toPacked()))->toEqual($version);

    $odd = new ApiVersion(1, 4, 357, 2);
    expect(ApiVersion::fromPacked($odd->toPacked()))->toEqual($odd);
    expect((string) $odd)->toBe('1.4.357 (variant 2)');

    expect($version->atLeast(1, 2))->toBeTrue();
    expect($version->atLeast(1, 3))->toBeTrue();
    expect($version->atLeast(1, 3, 1))->toBeFalse();
    expect($version->atLeast(2))->toBeFalse();
    expect((new ApiVersion())->atLeast(1))->toBeFalse();
});

it('answers availability from the loader, which is the resolution itself', function (): void {
    Bridge::load();

    /*
     * Unlike OpenGL there is no gap here between resolution and availability:
     * vkGetInstanceProcAddr returns NULL for a command above the instance's
     * API version and for an extension that was not enabled, so isAvailable()
     * IS the resolution the binding would do. A global command resolves with
     * no instance at all.
     */
    expect(Bridge::isAvailable('vkCreateInstance'))->toBeTrue();
    expect(Bridge::isAvailable('vkNoSuchCommandEverExisted'))->toBeFalse();
    expect(Bridge::procAddress('vkCreateInstance'))->toBeInt()->not->toBe(0);
});

it('allocates, writes, reads and frees a buffer', function (): void {
    Bridge::load();

    $ptr = Bridge::alloc(16);
    expect($ptr)->toBeInt()->not->toBe(0);

    $bytes = pack('l4', 1, -2, 0x8892, 64);
    expect(Bridge::write($ptr, 0, $bytes))->toBeTrue();

    $read = Bridge::read($ptr, 0, 16);
    expect($read)->toBeString();
    expect($read)->toBe($bytes);
    expect(array_values(unpack('l4', (string) $read)))->toBe([1, -2, 0x8892, 64]);
    expect(Bridge::read($ptr, 8, 4))->toBe(pack('l', 0x8892));

    Bridge::free($ptr);
});

it('narrows a refused read to null rather than handing back false', function (): void {
    Bridge::load();

    $captured = vulkanCaptureWarning(static fn () => Bridge::read(0, 0, 4));
    expect($captured['result'])->toBeNull();
    expect($captured['warning'])->toBeString();
});

it('makes a C string nothing else in PHP can make', function (): void {
    Bridge::load();

    $ptr = Bridge::cstring('VK_KHR_portability_enumeration');
    expect($ptr)->not->toBe(0);
    expect(Bridge::readCString($ptr))->toBe('VK_KHR_portability_enumeration');
    expect(Bridge::readCString(0))->toBe('');

    Bridge::free($ptr);
});

it('refuses a null device handle', function (): void {
    Bridge::load();

    // Address 0 is not a device, with or without an instance loaded. No
    // exception, no error side channel — false, and the process lives.
    $captured = vulkanCaptureWarning(static fn (): bool => Bridge::loadDevice(0));
    expect($captured['result'])->toBeFalse();
});

it('names the missing step when loadDevice is called before loadInstance', function (): void {
    /*
     * Two refusals that mean different things: "call Bridge::loadInstance()
     * first" is a missing step in your own code, and "not available on this
     * loader|instance|device" is the driver saying no. The first is only
     * observable in a process that has not made an instance yet, and Pest
     * shares one process across the suite — so this claim gets its own.
     */
    $script = <<<'PHP'
        require %s;
        Jovian\Bindings\Vulkan\Runtime\Bridge::load();
        set_error_handler(static function (int $n, string $m): bool { echo $m; return true; });
        var_export(Jovian\Bindings\Vulkan\Runtime\Bridge::loadDevice(1234));
        PHP;
    $script = sprintf($script, var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true));
    $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1';
    $output = (string) shell_exec($command);

    expect($output)->toContain('call Bridge::loadInstance() first');
    expect($output)->toContain('false');
});
