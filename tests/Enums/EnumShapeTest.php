<?php

declare(strict_types=1);

/*
| The house style, measured on the emitted enums rather than remembered:
| int-backed, FULLY UPPERCASE cases, no class constants, unique values.
*/

/** @return list<string> */
function vulkanEnumFiles(): array
{
    $files = glob(dirname(__DIR__, 2) . '/src/Enums/*.php') ?: [];
    sort($files);

    return $files;
}

/** @return list<class-string> */
function vulkanEnumFqcns(): array
{
    return array_map(
        static fn (string $file): string => 'Jovian\\Bindings\\Vulkan\\Enums\\' . basename($file, '.php'),
        vulkanEnumFiles(),
    );
}

it('emits 114 int-backed enums carrying 1224 cases', function (): void {
    $fqcns = vulkanEnumFqcns();
    expect($fqcns)->toHaveCount(114);

    $cases = 0;
    foreach ($fqcns as $fqcn) {
        expect(enum_exists($fqcn))->toBeTrue("{$fqcn} is not an enum");
        $reflection = new ReflectionEnum($fqcn);
        expect((string) $reflection->getBackingType())->toBe('int', "{$fqcn} is not int-backed");
        expect($fqcn::cases())->not->toBeEmpty("{$fqcn} has no cases");
        $cases += count($fqcn::cases());
    }

    expect($cases)->toBe(1224);
});

it('names every case in FULLY UPPERCASE', function (): void {
    foreach (vulkanEnumFqcns() as $fqcn) {
        foreach ($fqcn::cases() as $case) {
            expect($case->name)->toBe(strtoupper($case->name), "{$fqcn}::{$case->name}");
            expect(preg_match('/^[A-Z_][A-Z0-9_]*$/', $case->name))->toBe(1, "{$fqcn}::{$case->name}");
        }
    }
});

it('carries one case per value, and would record any name it had to drop', function (): void {
    $definitions = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/scripts/Generator/enums.json'),
        true,
    );
    expect($definitions)->toHaveCount(114);

    $aliases = 0;
    foreach (vulkanEnumFiles() as $file) {
        $name = basename($file, '.php');
        $fqcn = 'Jovian\\Bindings\\Vulkan\\Enums\\' . $name;
        $values = array_map(static fn ($case): int => $case->value, $fqcn::cases());
        expect($values)->toBe(array_values(array_unique($values)), "{$fqcn} has a duplicate backing value");

        $source = (string) file_get_contents($file);
        if (preg_match_all('/^\s+\*\s{3}(\w+) = (-?\d+) — same value as (\w+)\.$/m', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $aliases++;
                expect(in_array((int) $match[2], $values, true))
                    ->toBeTrue("{$fqcn} aliases {$match[1]} to a value no case carries");
            }
        }
        expect($definitions[$name]['aliases'])->toHaveCount(count($matches ?? []));
    }

    /*
     * Zero, today, and that is a fact about vk.xml rather than about this
     * package: the registry spells its synonyms with `alias=`, which is the
     * registry saying two names mean one constant, and those are skipped
     * outright. A second NAME carrying an already-used VALUE is a different
     * thing and would be recorded in the docblock and in enums.json; nothing
     * in scope does it. The mechanism is exercised by the assertion above,
     * which reads whatever is there.
     */
    expect($aliases)->toBe(0);
});

it('declares no class constants anywhere in src', function (): void {
    $root = dirname(__DIR__, 2) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $offenders = [];
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        $source = preg_replace('#/\*.*?\*/#s', ' ', $source) ?? $source;
        if (preg_match('/\bconst\s+[A-Z]/', $source) === 1) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('emits a bitmask enum but never types a mask with it', function (): void {
    // VkImageUsageFlags reaches VkImageUsageFlagBits, so the enum exists...
    expect(enum_exists(Jovian\Bindings\Vulkan\Enums\VkImageUsageFlagBits::class))->toBeTrue();

    // ...and VkImageCreateInfo.usage is still a plain int, because
    // `Bits::A | Bits::B` is a TypeError in PHP. Write `->value | ->value`.
    $usage = (new ReflectionClass(Jovian\Bindings\Vulkan\Structs\VkImageCreateInfo::class))
        ->getConstructor()
        ->getParameters();
    $byName = [];
    foreach ($usage as $parameter) {
        $byName[$parameter->getName()] = (string) $parameter->getType();
    }
    expect($byName['usage'])->toBe('int');

    // The single-bit declaration next to it IS typed: `samples` is declared
    // VkSampleCountFlagBits in the registry, one value rather than a mask.
    expect($byName['samples'])->toBe('Jovian\Bindings\Vulkan\Enums\VkSampleCountFlagBits|int');
});
