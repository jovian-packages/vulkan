<?php

declare(strict_types=1);

use Jovian\Bindings\Vulkan\Generator\Annotation;
use Jovian\Bindings\Vulkan\Generator\AnnotationParser;

/*
| The projected surface is exactly ext-vulkan's annotated surface: 267 command
| methods + 368 x 4 struct methods + 12 hand-written Bridge calls = the
| extension's 1751. The gate proves this from the source tree; this proves it
| from the generator's own parser, so a bug in one is not a bug in both.
*/

it('projects every annotation and nothing else', function (): void {
    $parsed = AnnotationParser::parseDirectory(vulkanExtRoot());
    $annotations = $parsed['annotations'];

    expect($annotations)->toHaveCount(1751);
    expect($parsed['reserved'])->toBe(0);

    $bridge = array_values(array_filter($annotations, static fn (Annotation $a): bool => $a->isBridge()));
    $structs = array_values(array_filter($annotations, static fn (Annotation $a): bool => $a->isStruct()));
    $commands = array_values(array_filter(
        $annotations,
        static fn (Annotation $a): bool => ! $a->isBridge() && ! $a->isStruct(),
    ));

    expect($bridge)->toHaveCount(12);
    expect($structs)->toHaveCount(1472);
    expect($commands)->toHaveCount(267);

    foreach ($commands as $annotation) {
        $fqcn = 'Jovian\\Bindings\\Vulkan\\' . $annotation->directory() . '\\' . $annotation->shortClass();
        expect(class_exists($fqcn))->toBeTrue("{$fqcn} is not projected");
        expect(method_exists($fqcn, $annotation->method))
            ->toBeTrue("{$fqcn}::{$annotation->method} is not projected");
    }

    foreach ($structs as $annotation) {
        $fqcn = 'Jovian\\Bindings\\Vulkan\\Structs\\' . $annotation->shortClass();
        expect(class_exists($fqcn))->toBeTrue("{$fqcn} is not projected");
        expect(method_exists($fqcn, $annotation->method))
            ->toBeTrue("{$fqcn}::{$annotation->method} is not projected");
    }

    foreach ($bridge as $annotation) {
        expect(method_exists(Jovian\Bindings\Vulkan\Runtime\Bridge::class, $annotation->method))
            ->toBeTrue("Runtime\\Bridge::{$annotation->method} is not projected");
    }
});

it('puts every class in the directory the annotation names', function (): void {
    $parsed = AnnotationParser::parseDirectory(vulkanExtRoot());

    $classes = [];
    foreach ($parsed['annotations'] as $annotation) {
        if ($annotation->isBridge()) {
            continue;
        }
        $classes[$annotation->directory() . '\\' . $annotation->shortClass()] = true;
    }

    // 5 core feature classes + 8 extension classes + 368 structs.
    expect(array_keys($classes))->toHaveCount(381);
    expect(array_keys($classes))->toContain('VK\VK10', 'VK\VK14', 'Ext\KHRSurface', 'Structs\VkExtent2D');
});

it('makes the struct tier four bound methods plus two conversions', function (): void {
    $parsed = AnnotationParser::parseDirectory(vulkanExtRoot());

    $byStruct = [];
    foreach ($parsed['annotations'] as $annotation) {
        if ($annotation->isStruct()) {
            $byStruct[$annotation->shortClass()][] = $annotation->method;
        }
    }

    expect($byStruct)->toHaveCount(368);
    foreach ($byStruct as $name => $methods) {
        sort($methods);
        expect($methods)->toBe(['pack', 'packInto', 'size', 'unpack'], $name);

        $fqcn = 'Jovian\\Bindings\\Vulkan\\Structs\\' . $name;
        // The two this layer adds: the constructor read in both directions.
        expect(method_exists($fqcn, 'toArray'))->toBeTrue("{$fqcn}::toArray");
        expect(method_exists($fqcn, 'fromArray'))->toBeTrue("{$fqcn}::fromArray");

        // pack() and packInto() are instance methods; the object already holds
        // its members, so handing them back to it would not be a value object.
        expect((new ReflectionMethod($fqcn, 'pack'))->isStatic())->toBeFalse($name);
        expect((new ReflectionMethod($fqcn, 'packInto'))->isStatic())->toBeFalse($name);
        expect((new ReflectionMethod($fqcn, 'unpack'))->isStatic())->toBeTrue($name);
        expect((new ReflectionMethod($fqcn, 'size'))->isStatic())->toBeTrue($name);
    }
});

it('types an enum return as documentation and hands back an int', function (): void {
    /*
     * `vkCreateInstance(): VkResult|int` RETURNS AN INT. Converting would be
     * behaviour, and this layer adds none — jovian/metal's rule, inherited on
     * purpose, because diverging from the sibling package would be worse than
     * the wart. `VkResult::tryFrom($r)` is the idiom; `$r === VkResult::SUCCESS`
     * is always false.
     */
    $method = new ReflectionMethod(Jovian\Bindings\Vulkan\VK\VK10::class, 'vkCreateInstance');
    expect((string) $method->getReturnType())->toBe('Jovian\Bindings\Vulkan\Enums\VkResult|int');

    $parameter = (new ReflectionMethod(Jovian\Bindings\Vulkan\VK\VK10::class, 'vkCmdBindPipeline'))
        ->getParameters()[1];
    expect((string) $parameter->getType())->toBe('Jovian\Bindings\Vulkan\Enums\VkPipelineBindPoint|int');
});
