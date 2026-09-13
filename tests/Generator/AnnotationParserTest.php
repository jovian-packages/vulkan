<?php

declare(strict_types=1);

use Jovian\Bindings\Vulkan\Generator\AnnotationParser;
use Jovian\Bindings\Vulkan\Generator\TypeName;

it('parses a @zep line into a typed annotation', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'jvk') . '.h';
    file_put_contents($path, <<<'H'
    /*@audit feature VK\VK10 VK_VERSION_1_0 137 */
    /*@zep VK\VK10 vkCreateInstance(int pCreateInfo, int pAllocator, int pInstance) -> int */
    void phpvk_vk10_vkcreateinstance(zval *pCreateInfo, zval *pAllocator, zval *pInstance);
    /*@zep Struct\VkExtent2D pack(array members) -> int */
    /*@reserved VK\VK10 void vkNotBound(PFN_vkVoidFunction cb) — a callback */
    H);

    $parsed = AnnotationParser::parseFile($path);
    unlink($path);

    expect($parsed['reserved'])->toBe(1);
    expect($parsed['annotations'])->toHaveCount(2);

    $command = $parsed['annotations'][0];
    expect($command->classPath)->toBe('VK\VK10');
    expect($command->method)->toBe('vkCreateInstance');
    expect($command->returnType)->toBe('int');
    expect($command->family())->toBe('VK');
    expect($command->directory())->toBe('VK');
    expect($command->shortClass())->toBe('VK10');
    expect($command->isStruct())->toBeFalse();
    expect($command->params)->toBe([
        ['type' => 'int', 'name' => 'pCreateInfo'],
        ['type' => 'int', 'name' => 'pAllocator'],
        ['type' => 'int', 'name' => 'pInstance'],
    ]);

    $struct = $parsed['annotations'][1];
    expect($struct->isStruct())->toBeTrue();
    expect($struct->family())->toBe('Struct');
    expect($struct->directory())->toBe('Structs');
    expect($struct->shortClass())->toBe('VkExtent2D');
});

it('refuses a malformed annotation rather than skipping it', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'jvk') . '.h';
    file_put_contents($path, "/*@zep VK\\VK10 vkBroken(int target -> void */\n");

    try {
        expect(static fn () => AnnotationParser::parseFile($path))
            ->toThrow(RuntimeException::class);
    } finally {
        unlink($path);
    }
});

it('maps the extension\'s scalars to PHP types', function (): void {
    expect(TypeName::scalar('int'))->toBe('int');
    expect(TypeName::scalar('double'))->toBe('float');
    expect(TypeName::scalar('bool'))->toBe('bool');
    expect(TypeName::scalar('string'))->toBe('string');
    expect(TypeName::scalar('array'))->toBe('array');
    expect(TypeName::scalar('var'))->toBe('?string');
    expect(TypeName::scalar('void'))->toBe('void');
    expect(static fn () => TypeName::scalar('resource'))->toThrow(RuntimeException::class);
});

it('names enum cases mechanically (D2)', function (): void {
    $case = static fn (string $constant, string $type): string => TypeName::constantCase($constant, $type)['case'];

    // The prefix is the type name in UPPER_SNAKE; a trailing FLAG_BITS comes
    // off, because the registry does not repeat it in the constants.
    expect($case('VK_STRUCTURE_TYPE_APPLICATION_INFO', 'VkStructureType'))->toBe('APPLICATION_INFO');
    expect($case('VK_IMAGE_USAGE_TRANSFER_SRC_BIT', 'VkImageUsageFlagBits'))->toBe('TRANSFER_SRC_BIT');

    // VkResult's constants do not repeat the type name at all, so the ladder
    // falls through to VK_.
    expect($case('VK_SUCCESS', 'VkResult'))->toBe('SUCCESS');
    expect($case('VK_ERROR_OUT_OF_HOST_MEMORY', 'VkResult'))->toBe('ERROR_OUT_OF_HOST_MEMORY');

    // A stem that starts with a digit is not a legal PHP identifier, so the
    // prefix's last word goes back on the front.
    expect($case('VK_SAMPLE_COUNT_1_BIT', 'VkSampleCountFlagBits'))->toBe('COUNT_1_BIT');
    expect($case('VK_IMAGE_TYPE_2D', 'VkImageType'))->toBe('TYPE_2D');

    // A vendor tag is a word, not three: EXT must not become E_X_T.
    expect(TypeName::typeWords('VkDebugUtilsMessageSeverityFlagBitsEXT'))->toBe(
        ['VK', 'DEBUG', 'UTILS', 'MESSAGE', 'SEVERITY', 'FLAG', 'BITS', 'EXT'],
    );
    expect($case('VK_DEBUG_UTILS_MESSAGE_SEVERITY_VERBOSE_BIT_EXT', 'VkDebugUtilsMessageSeverityFlagBitsEXT'))
        ->toBe('VERBOSE_BIT_EXT');
});

it('replays the extension\'s duplicate-tail class path rule', function (): void {
    $parsed = AnnotationParser::parseDirectory(vulkanExtRoot());
    $byPath = [];
    foreach ($parsed['annotations'] as $annotation) {
        $byPath[$annotation->classPath] ??= $annotation;
    }

    // VK\VK10 keeps its fourth segment and so does Struct\VkExtent2D;
    // Bridge\Bridge collapses. Getting this wrong emits `use` lines for
    // classes that do not exist, and PHP says nothing until one is called.
    expect($byPath['VK\VK10']->extFqcn())->toBe('Vulkan\VK\VK10\VK10');
    expect($byPath['Ext\KHRSurface']->extFqcn())->toBe('Vulkan\Ext\KHRSurface\KHRSurface');
    expect($byPath['Struct\VkExtent2D']->extFqcn())->toBe('Vulkan\Struct\VkExtent2D\VkExtent2D');
    expect($byPath['Bridge\Bridge']->extFqcn())->toBe('Vulkan\Bridge\Bridge');
});
