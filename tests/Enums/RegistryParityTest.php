<?php

declare(strict_types=1);

use Jovian\Bindings\Vulkan\Enums\VkAccessFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkAttachmentLoadOp;
use Jovian\Bindings\Vulkan\Enums\VkAttachmentStoreOp;
use Jovian\Bindings\Vulkan\Enums\VkBlendFactor;
use Jovian\Bindings\Vulkan\Enums\VkBlendOp;
use Jovian\Bindings\Vulkan\Enums\VkBufferUsageFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkColorComponentFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkCommandBufferLevel;
use Jovian\Bindings\Vulkan\Enums\VkCommandBufferUsageFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkComponentSwizzle;
use Jovian\Bindings\Vulkan\Enums\VkCullModeFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkFormat;
use Jovian\Bindings\Vulkan\Enums\VkFrontFace;
use Jovian\Bindings\Vulkan\Enums\VkImageAspectFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkImageLayout;
use Jovian\Bindings\Vulkan\Enums\VkImageTiling;
use Jovian\Bindings\Vulkan\Enums\VkImageType;
use Jovian\Bindings\Vulkan\Enums\VkImageUsageFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkImageViewType;
use Jovian\Bindings\Vulkan\Enums\VkInstanceCreateFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkLogicOp;
use Jovian\Bindings\Vulkan\Enums\VkMemoryPropertyFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkPhysicalDeviceType;
use Jovian\Bindings\Vulkan\Enums\VkPipelineBindPoint;
use Jovian\Bindings\Vulkan\Enums\VkPipelineStageFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkPolygonMode;
use Jovian\Bindings\Vulkan\Enums\VkPrimitiveTopology;
use Jovian\Bindings\Vulkan\Enums\VkQueueFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkResult;
use Jovian\Bindings\Vulkan\Enums\VkSampleCountFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkShaderStageFlagBits;
use Jovian\Bindings\Vulkan\Enums\VkSharingMode;
use Jovian\Bindings\Vulkan\Enums\VkStructureType;
use Jovian\Bindings\Vulkan\Enums\VkSubpassContents;
use Jovian\Bindings\Vulkan\Enums\VkVertexInputRate;
use Jovian\Bindings\Vulkan\Generator\VkRegistry;

/*
| Enum values are re-measured against the one registry, and the constants
| ext-vulkan's own proof carries inline are pinned by name.
|
| `examples/proof_headless.php` in the extension opens with 74
| `const VK_* = …;` lines, each cited to the `<enums>` block it came from,
| "because this package did not exist yet" (ext-vulkan's rule 10). Those
| literals are exactly what these enums replace, so every one of them is
| pinned here with the extension's own spelling of its value.
*/

it('pins the literals ext-vulkan\'s own proof cites', function (): void {
    expect(VkResult::SUCCESS->value)->toBe(0, 'VK_SUCCESS');
    expect(VkStructureType::APPLICATION_INFO->value)->toBe(0, 'VK_STRUCTURE_TYPE_APPLICATION_INFO');
    expect(VkStructureType::INSTANCE_CREATE_INFO->value)->toBe(1, 'VK_STRUCTURE_TYPE_INSTANCE_CREATE_INFO');
    expect(VkStructureType::DEVICE_QUEUE_CREATE_INFO->value)->toBe(2, 'VK_STRUCTURE_TYPE_DEVICE_QUEUE_CREATE_INFO');
    expect(VkStructureType::DEVICE_CREATE_INFO->value)->toBe(3, 'VK_STRUCTURE_TYPE_DEVICE_CREATE_INFO');
    expect(VkStructureType::SUBMIT_INFO->value)->toBe(4, 'VK_STRUCTURE_TYPE_SUBMIT_INFO');
    expect(VkStructureType::MEMORY_ALLOCATE_INFO->value)->toBe(5, 'VK_STRUCTURE_TYPE_MEMORY_ALLOCATE_INFO');
    expect(VkStructureType::BUFFER_CREATE_INFO->value)->toBe(12, 'VK_STRUCTURE_TYPE_BUFFER_CREATE_INFO');
    expect(VkStructureType::IMAGE_CREATE_INFO->value)->toBe(14, 'VK_STRUCTURE_TYPE_IMAGE_CREATE_INFO');
    expect(VkStructureType::IMAGE_VIEW_CREATE_INFO->value)->toBe(15, 'VK_STRUCTURE_TYPE_IMAGE_VIEW_CREATE_INFO');
    expect(VkStructureType::SHADER_MODULE_CREATE_INFO->value)->toBe(16, 'VK_STRUCTURE_TYPE_SHADER_MODULE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_SHADER_STAGE_CREATE_INFO->value)->toBe(18, 'VK_STRUCTURE_TYPE_PIPELINE_SHADER_STAGE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_VERTEX_INPUT_STATE_CREATE_INFO->value)->toBe(19, 'VK_STRUCTURE_TYPE_PIPELINE_VERTEX_INPUT_STATE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_INPUT_ASSEMBLY_STATE_CREATE_INFO->value)->toBe(20, 'VK_STRUCTURE_TYPE_PIPELINE_INPUT_ASSEMBLY_STATE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_VIEWPORT_STATE_CREATE_INFO->value)->toBe(22, 'VK_STRUCTURE_TYPE_PIPELINE_VIEWPORT_STATE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_RASTERIZATION_STATE_CREATE_INFO->value)->toBe(23, 'VK_STRUCTURE_TYPE_PIPELINE_RASTERIZATION_STATE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_MULTISAMPLE_STATE_CREATE_INFO->value)->toBe(24, 'VK_STRUCTURE_TYPE_PIPELINE_MULTISAMPLE_STATE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_COLOR_BLEND_STATE_CREATE_INFO->value)->toBe(26, 'VK_STRUCTURE_TYPE_PIPELINE_COLOR_BLEND_STATE_CREATE_INFO');
    expect(VkStructureType::GRAPHICS_PIPELINE_CREATE_INFO->value)->toBe(28, 'VK_STRUCTURE_TYPE_GRAPHICS_PIPELINE_CREATE_INFO');
    expect(VkStructureType::PIPELINE_LAYOUT_CREATE_INFO->value)->toBe(30, 'VK_STRUCTURE_TYPE_PIPELINE_LAYOUT_CREATE_INFO');
    expect(VkStructureType::FRAMEBUFFER_CREATE_INFO->value)->toBe(37, 'VK_STRUCTURE_TYPE_FRAMEBUFFER_CREATE_INFO');
    expect(VkStructureType::RENDER_PASS_CREATE_INFO->value)->toBe(38, 'VK_STRUCTURE_TYPE_RENDER_PASS_CREATE_INFO');
    expect(VkStructureType::COMMAND_POOL_CREATE_INFO->value)->toBe(39, 'VK_STRUCTURE_TYPE_COMMAND_POOL_CREATE_INFO');
    expect(VkStructureType::COMMAND_BUFFER_ALLOCATE_INFO->value)->toBe(40, 'VK_STRUCTURE_TYPE_COMMAND_BUFFER_ALLOCATE_INFO');
    expect(VkStructureType::COMMAND_BUFFER_BEGIN_INFO->value)->toBe(42, 'VK_STRUCTURE_TYPE_COMMAND_BUFFER_BEGIN_INFO');
    expect(VkStructureType::RENDER_PASS_BEGIN_INFO->value)->toBe(43, 'VK_STRUCTURE_TYPE_RENDER_PASS_BEGIN_INFO');
    expect(VkInstanceCreateFlagBits::ENUMERATE_PORTABILITY_BIT_KHR->value)->toBe(0x00000001, 'VK_INSTANCE_CREATE_ENUMERATE_PORTABILITY_BIT_KHR');
    expect(VkPhysicalDeviceType::INTEGRATED_GPU->value)->toBe(1, 'VK_PHYSICAL_DEVICE_TYPE_INTEGRATED_GPU');
    expect(VkQueueFlagBits::GRAPHICS_BIT->value)->toBe(0x00000001, 'VK_QUEUE_GRAPHICS_BIT');
    expect(VkFormat::R8G8B8A8_UNORM->value)->toBe(37, 'VK_FORMAT_R8G8B8A8_UNORM');
    expect(VkFormat::R32G32_SFLOAT->value)->toBe(103, 'VK_FORMAT_R32G32_SFLOAT');
    expect(VkImageType::TYPE_2D->value)->toBe(1, 'VK_IMAGE_TYPE_2D');
    expect(VkImageTiling::OPTIMAL->value)->toBe(0, 'VK_IMAGE_TILING_OPTIMAL');
    expect(VkSharingMode::EXCLUSIVE->value)->toBe(0, 'VK_SHARING_MODE_EXCLUSIVE');
    expect(VkImageViewType::TYPE_2D->value)->toBe(1, 'VK_IMAGE_VIEW_TYPE_2D');
    expect(VkSampleCountFlagBits::COUNT_1_BIT->value)->toBe(0x00000001, 'VK_SAMPLE_COUNT_1_BIT');
    expect(VkImageUsageFlagBits::TRANSFER_SRC_BIT->value)->toBe(0x00000001, 'VK_IMAGE_USAGE_TRANSFER_SRC_BIT');
    expect(VkImageUsageFlagBits::COLOR_ATTACHMENT_BIT->value)->toBe(0x00000010, 'VK_IMAGE_USAGE_COLOR_ATTACHMENT_BIT');
    expect(VkImageLayout::UNDEFINED->value)->toBe(0, 'VK_IMAGE_LAYOUT_UNDEFINED');
    expect(VkImageLayout::COLOR_ATTACHMENT_OPTIMAL->value)->toBe(2, 'VK_IMAGE_LAYOUT_COLOR_ATTACHMENT_OPTIMAL');
    expect(VkImageLayout::TRANSFER_SRC_OPTIMAL->value)->toBe(6, 'VK_IMAGE_LAYOUT_TRANSFER_SRC_OPTIMAL');
    expect(VkImageAspectFlagBits::COLOR_BIT->value)->toBe(0x00000001, 'VK_IMAGE_ASPECT_COLOR_BIT');
    expect(VkComponentSwizzle::IDENTITY->value)->toBe(0, 'VK_COMPONENT_SWIZZLE_IDENTITY');
    expect(VkMemoryPropertyFlagBits::DEVICE_LOCAL_BIT->value)->toBe(0x00000001, 'VK_MEMORY_PROPERTY_DEVICE_LOCAL_BIT');
    expect(VkMemoryPropertyFlagBits::HOST_VISIBLE_BIT->value)->toBe(0x00000002, 'VK_MEMORY_PROPERTY_HOST_VISIBLE_BIT');
    expect(VkMemoryPropertyFlagBits::HOST_COHERENT_BIT->value)->toBe(0x00000004, 'VK_MEMORY_PROPERTY_HOST_COHERENT_BIT');
    expect(VkAttachmentLoadOp::CLEAR->value)->toBe(1, 'VK_ATTACHMENT_LOAD_OP_CLEAR');
    expect(VkAttachmentLoadOp::DONT_CARE->value)->toBe(2, 'VK_ATTACHMENT_LOAD_OP_DONT_CARE');
    expect(VkAttachmentStoreOp::STORE->value)->toBe(0, 'VK_ATTACHMENT_STORE_OP_STORE');
    expect(VkAttachmentStoreOp::DONT_CARE->value)->toBe(1, 'VK_ATTACHMENT_STORE_OP_DONT_CARE');
    expect(VkPipelineBindPoint::GRAPHICS->value)->toBe(0, 'VK_PIPELINE_BIND_POINT_GRAPHICS');
    expect(VkPipelineStageFlagBits::COLOR_ATTACHMENT_OUTPUT_BIT->value)->toBe(0x00000400, 'VK_PIPELINE_STAGE_COLOR_ATTACHMENT_OUTPUT_BIT');
    expect(VkPipelineStageFlagBits::TRANSFER_BIT->value)->toBe(0x00001000, 'VK_PIPELINE_STAGE_TRANSFER_BIT');
    expect(VkAccessFlagBits::COLOR_ATTACHMENT_WRITE_BIT->value)->toBe(0x00000100, 'VK_ACCESS_COLOR_ATTACHMENT_WRITE_BIT');
    expect(VkAccessFlagBits::TRANSFER_READ_BIT->value)->toBe(0x00000800, 'VK_ACCESS_TRANSFER_READ_BIT');
    expect(VkShaderStageFlagBits::VERTEX_BIT->value)->toBe(0x00000001, 'VK_SHADER_STAGE_VERTEX_BIT');
    expect(VkShaderStageFlagBits::FRAGMENT_BIT->value)->toBe(0x00000010, 'VK_SHADER_STAGE_FRAGMENT_BIT');
    expect(VkVertexInputRate::VERTEX->value)->toBe(0, 'VK_VERTEX_INPUT_RATE_VERTEX');
    expect(VkPrimitiveTopology::TRIANGLE_LIST->value)->toBe(3, 'VK_PRIMITIVE_TOPOLOGY_TRIANGLE_LIST');
    expect(VkPolygonMode::FILL->value)->toBe(0, 'VK_POLYGON_MODE_FILL');
    expect(VkCullModeFlagBits::NONE->value)->toBe(0, 'VK_CULL_MODE_NONE');
    expect(VkFrontFace::COUNTER_CLOCKWISE->value)->toBe(0, 'VK_FRONT_FACE_COUNTER_CLOCKWISE');
    expect(VkBlendFactor::ZERO->value)->toBe(0, 'VK_BLEND_FACTOR_ZERO');
    expect(VkBlendFactor::ONE->value)->toBe(1, 'VK_BLEND_FACTOR_ONE');
    expect(VkBlendOp::ADD->value)->toBe(0, 'VK_BLEND_OP_ADD');
    expect(VkLogicOp::COPY->value)->toBe(3, 'VK_LOGIC_OP_COPY');
    expect(VkBufferUsageFlagBits::TRANSFER_DST_BIT->value)->toBe(0x00000002, 'VK_BUFFER_USAGE_TRANSFER_DST_BIT');
    expect(VkBufferUsageFlagBits::VERTEX_BUFFER_BIT->value)->toBe(0x00000080, 'VK_BUFFER_USAGE_VERTEX_BUFFER_BIT');
    expect(VkCommandBufferLevel::PRIMARY->value)->toBe(0, 'VK_COMMAND_BUFFER_LEVEL_PRIMARY');
    expect(VkCommandBufferUsageFlagBits::ONE_TIME_SUBMIT_BIT->value)->toBe(0x00000001, 'VK_COMMAND_BUFFER_USAGE_ONE_TIME_SUBMIT_BIT');
    expect(VkSubpassContents::INLINE->value)->toBe(0, 'VK_SUBPASS_CONTENTS_INLINE');
});

it('leaves three of the 74 alone, and says which', function (): void {
    /*
     * Three of the proof's 74 literals are not enum members and do not become
     * cases:
     *
     *   VK_SUBPASS_EXTERNAL   `<enums name="API Constants">`, value (~0U)
     *   VK_WHOLE_SIZE         the same block, (~0ULL) — the 64-bit wrap, -1
     *   VK_COLOR_COMPONENT_RGBA_BITS   the proof's own name for four
     *                         VkColorComponentFlagBits bits OR'd together
     *
     * The first two stay inline in the typed proof with their citation, because
     * D2 emits an enum for an `<enums type="enum"|"bitmask">` block and API
     * Constants is neither. The third is written as the OR it always was.
     */
    $registry = VkRegistry::load(vulkanExtRoot());

    expect($registry->reachesEnum('VkColorComponentFlags'))->toBe('VkColorComponentFlagBits');
    expect(
        VkColorComponentFlagBits::R_BIT->value
        | VkColorComponentFlagBits::G_BIT->value
        | VkColorComponentFlagBits::B_BIT->value
        | VkColorComponentFlagBits::A_BIT->value
    )->toBe(0x0000000F);

    expect(enum_exists('Jovian\\Bindings\\Vulkan\\Enums\\VkApiConstants'))->toBeFalse();
});

it('re-derives every emitted case from vk.xml without reading a sidecar', function (): void {
    $registry = VkRegistry::load(vulkanExtRoot());
    $blocks = $registry->scopedBlockNames();

    $files = glob(dirname(__DIR__, 2) . '/src/Enums/*.php') ?: [];
    expect($files)->toHaveCount(114);

    $checked = 0;
    foreach ($files as $file) {
        $name = basename($file, '.php');
        $built = $registry->buildEnum($name, $blocks);
        expect($built['enum'])->not->toBeNull("{$name} is emitted but carries no in-scope case");

        $fqcn = 'Jovian\\Bindings\\Vulkan\\Enums\\' . $name;
        $emitted = [];
        foreach ($fqcn::cases() as $case) {
            $emitted[$case->name] = $case->value;
        }

        foreach ($built['enum']->cases as $case) {
            expect(array_key_exists($case['case'], $emitted))
                ->toBeTrue("{$name}::{$case['case']} is missing");
            expect($emitted[$case['case']])->toBe($case['value'], "{$name}::{$case['case']}");
            $checked++;
        }
        expect(count($emitted))->toBe(count($built['enum']->cases), "{$name} has cases vk.xml does not");
    }

    expect($checked)->toBe(1224);
});

