<?php

/*
 * proof_headless_typed.php — ext-vulkan's own proof, typed.
 *
 * This is `php-io-extensions/vulkan/vulkan/examples/proof_headless.php` with
 * exactly two changes, and it renders the same bytes:
 *
 *   1. the 74 inline `const VK_* = …; // vk.xml` literals are gone. 71 of them
 *      are enum cases now; one (`VK_COLOR_COMPONENT_RGBA_BITS`) was always the
 *      OR of four `VkColorComponentFlagBits` bits and is written as one; and
 *      two — `VK_SUBPASS_EXTERNAL` and `VK_WHOLE_SIZE` — stay inline with
 *      their citation, because `<enums name="API Constants">` is not an enum
 *      block and D2 emits none for it.
 *   2. every `Struct::pack([...])` array is a readonly value object, and every
 *      `unpack()` hands one back instead of an array.
 *
 * Same calls, in the same order, with the same two pixels checked byte for
 * byte:
 *
 *     (32, 32) = 255, 128, 64, 255   the fragment shader's colour
 *     ( 0,  0) =   0,   0,  0, 255   the render pass's clear colour
 *
 * Everything opinionated is still HERE and not in either layer below: every
 * sType this script does not let default, the portability extensions MoltenVK
 * needs, the memory types, the pipeline state, and which physical device to
 * pick. jovian/vulkan adds types and enums and nothing else.
 *
 * Prints PROOF_HEADLESS_TYPED_OK when all of that held; on any failure it
 * writes PROOF_HEADLESS_TYPED_FAILED: <why> to stderr and exits 1.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

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
use Jovian\Bindings\Vulkan\Enums\VkSubpassContents;
use Jovian\Bindings\Vulkan\Enums\VkVertexInputRate;
use Jovian\Bindings\Vulkan\Runtime\Bridge;
use Jovian\Bindings\Vulkan\Structs\VkApplicationInfo;
use Jovian\Bindings\Vulkan\Structs\VkAttachmentDescription;
use Jovian\Bindings\Vulkan\Structs\VkAttachmentReference;
use Jovian\Bindings\Vulkan\Structs\VkBufferCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkBufferImageCopy;
use Jovian\Bindings\Vulkan\Structs\VkClearColorValue;
use Jovian\Bindings\Vulkan\Structs\VkClearValue;
use Jovian\Bindings\Vulkan\Structs\VkCommandBufferAllocateInfo;
use Jovian\Bindings\Vulkan\Structs\VkCommandBufferBeginInfo;
use Jovian\Bindings\Vulkan\Structs\VkCommandPoolCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkComponentMapping;
use Jovian\Bindings\Vulkan\Structs\VkDeviceCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkDeviceQueueCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkExtensionProperties;
use Jovian\Bindings\Vulkan\Structs\VkExtent2D;
use Jovian\Bindings\Vulkan\Structs\VkExtent3D;
use Jovian\Bindings\Vulkan\Structs\VkFramebufferCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkGraphicsPipelineCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkImageCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkImageSubresourceLayers;
use Jovian\Bindings\Vulkan\Structs\VkImageSubresourceRange;
use Jovian\Bindings\Vulkan\Structs\VkImageViewCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkInstanceCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkMemoryAllocateInfo;
use Jovian\Bindings\Vulkan\Structs\VkMemoryRequirements;
use Jovian\Bindings\Vulkan\Structs\VkOffset2D;
use Jovian\Bindings\Vulkan\Structs\VkOffset3D;
use Jovian\Bindings\Vulkan\Structs\VkPhysicalDeviceMemoryProperties;
use Jovian\Bindings\Vulkan\Structs\VkPhysicalDeviceProperties;
use Jovian\Bindings\Vulkan\Structs\VkPipelineColorBlendAttachmentState;
use Jovian\Bindings\Vulkan\Structs\VkPipelineColorBlendStateCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineInputAssemblyStateCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineLayoutCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineMultisampleStateCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineRasterizationStateCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineShaderStageCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineVertexInputStateCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkPipelineViewportStateCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkQueueFamilyProperties;
use Jovian\Bindings\Vulkan\Structs\VkRect2D;
use Jovian\Bindings\Vulkan\Structs\VkRenderPassBeginInfo;
use Jovian\Bindings\Vulkan\Structs\VkRenderPassCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkShaderModuleCreateInfo;
use Jovian\Bindings\Vulkan\Structs\VkSubmitInfo;
use Jovian\Bindings\Vulkan\Structs\VkSubpassDependency;
use Jovian\Bindings\Vulkan\Structs\VkSubpassDescription;
use Jovian\Bindings\Vulkan\Structs\VkVertexInputAttributeDescription;
use Jovian\Bindings\Vulkan\Structs\VkVertexInputBindingDescription;
use Jovian\Bindings\Vulkan\Structs\VkViewport;
use Jovian\Bindings\Vulkan\Values\ApiVersion;
use Jovian\Bindings\Vulkan\VK\VK10;

/* --------------------------------------------------- the two that stay -----
 * `<enums name="API Constants">` is not an `<enums type="enum"|"bitmask">`
 * block, so D2 emits no PHP enum for it and these two keep their citation.
 */

// <enum name="VK_SUBPASS_EXTERNAL" value="(~0U)">
const VK_SUBPASS_EXTERNAL = 0xFFFFFFFF;

// <enum name="VK_WHOLE_SIZE" value="(~0ULL)"> — UINT64_MAX crosses as -1
// (ext-vulkan .okf/struct-tier.md, the 64-bit wrap).
const VK_WHOLE_SIZE = -1;

/* ------------------------------------------------------------------ script -- */

const WIDTH = 64;
const HEIGHT = 64;
const PIXEL_BYTES = 4;
const IMAGE_BYTES = WIDTH * HEIGHT * PIXEL_BYTES;

function fail(string $m): never
{
    fwrite(STDERR, "PROOF_HEADLESS_TYPED_FAILED: {$m}\n");
    exit(1);
}

/** Every block this script owns, newest last; all of them are freed at the end. */
$blocks = [];

/** Record a Bridge block (alloc / cstring / a value object's pack) and refuse a failed one. */
$keep = static function (int $ptr, string $what) use (&$blocks): int {
    if ($ptr === 0) {
        fail($what);
    }
    $blocks[] = $ptr;

    return $ptr;
};

/** Read a 64-bit handle a Vulkan out-parameter wrote into a block. */
$handleAt = static function (int $ptr, int $index = 0): int {
    return unpack('P', (string) Bridge::read($ptr, $index * 8, 8))[1];
};

/** Write a 64-bit handle into a block, for an array parameter. */
$putHandle = static function (int $ptr, int $index, int $handle): void {
    Bridge::write($ptr, $index * 8, pack('P', $handle)) || fail('Bridge::write handle');
};

/** Check a VkResult. The projection hands back an int; the enum is how you read it. */
$ok = static function (int $r, string $what): void {
    if ($r !== VkResult::SUCCESS->value) {
        fail("{$what} returned VkResult {$r} (" . (VkResult::tryFrom($r)?->name ?? 'unknown') . ')');
    }
};

/* -- 1. Instance ------------------------------------------------------------ */

Bridge::load() || fail('Bridge::load');

/*
 * MoltenVK is a portability driver: without VK_KHR_portability_enumeration and
 * the matching instance flag the loader reports zero physical devices — no
 * error, just an empty list. The driver's rule, not this package's.
 */
$isDarwin = PHP_OS_FAMILY === 'Darwin';
$instanceExtNames = $isDarwin ? ['VK_KHR_portability_enumeration'] : [];

$instanceExtPtrs = [];
foreach ($instanceExtNames as $name) {
    $instanceExtPtrs[] = $keep(Bridge::cstring($name), "Bridge::cstring({$name})");
}
$instanceExtArray = $keep(Bridge::alloc(max(1, count($instanceExtPtrs)) * 8), 'alloc instance extension array');
foreach ($instanceExtPtrs as $i => $p) {
    $putHandle($instanceExtArray, $i, $p);
}

$appName = $keep(Bridge::cstring('proof_headless'), 'Bridge::cstring(proof_headless)');
$appInfo = $keep((new VkApplicationInfo(
    pApplicationName: $appName,
    applicationVersion: 1,
    apiVersion: ApiVersion::make(1, 3, 0)->toPacked(),
))->pack(), 'VkApplicationInfo::pack');

$instanceInfo = $keep((new VkInstanceCreateInfo(
    flags: $isDarwin ? VkInstanceCreateFlagBits::ENUMERATE_PORTABILITY_BIT_KHR->value : 0,
    pApplicationInfo: $appInfo,
    enabledExtensionCount: count($instanceExtPtrs),
    ppEnabledExtensionNames: $instanceExtArray,
))->pack(), 'VkInstanceCreateInfo::pack');

$instanceOut = $keep(Bridge::alloc(8), 'alloc instance handle');
$ok(VK10::vkCreateInstance($instanceInfo, 0, $instanceOut), 'vkCreateInstance');
$instance = $handleAt($instanceOut);
$instance !== 0 || fail('vkCreateInstance succeeded but wrote a null handle');
Bridge::loadInstance($instance) || fail('Bridge::loadInstance');

/* -- 2. Physical device with a graphics queue family ------------------------ */

$countOut = $keep(Bridge::alloc(4), 'alloc count');
$readCount = static function () use ($countOut): int {
    return unpack('V', (string) Bridge::read($countOut, 0, 4))[1];
};

$ok(VK10::vkEnumeratePhysicalDevices($instance, $countOut, 0), 'vkEnumeratePhysicalDevices(count)');
$deviceCount = $readCount();
$deviceCount > 0 || fail('no physical devices');

$deviceList = $keep(Bridge::alloc($deviceCount * 8), 'alloc physical device list');
$ok(VK10::vkEnumeratePhysicalDevices($instance, $countOut, $deviceList), 'vkEnumeratePhysicalDevices(list)');

$propsBlock = $keep(Bridge::alloc(VkPhysicalDeviceProperties::size()), 'alloc VkPhysicalDeviceProperties');

$physicalDevice = 0;
$queueFamily = -1;
$deviceProps = null;
$preferredType = -1;

for ($i = 0; $i < $deviceCount; $i++) {
    $candidate = $handleAt($deviceList, $i);

    VK10::vkGetPhysicalDeviceQueueFamilyProperties($candidate, $countOut, 0);
    $familyCount = $readCount();
    if ($familyCount === 0) {
        continue;
    }

    $families = $keep(Bridge::alloc($familyCount * VkQueueFamilyProperties::size()), 'alloc queue families');
    VK10::vkGetPhysicalDeviceQueueFamilyProperties($candidate, $countOut, $families);

    $family = -1;
    for ($f = 0; $f < $familyCount; $f++) {
        $qf = VkQueueFamilyProperties::unpack($families + $f * VkQueueFamilyProperties::size());
        // queueFlags is a VkQueueFlags mask, so it is an int on both sides and
        // the enum case is unwrapped by hand: Bits::A | Bits::B is a TypeError.
        if (($qf->queueFlags & VkQueueFlagBits::GRAPHICS_BIT->value) !== 0 && $qf->queueCount > 0) {
            $family = $f;
            break;
        }
    }
    if ($family < 0) {
        continue;
    }

    VK10::vkGetPhysicalDeviceProperties($candidate, $propsBlock);
    $p = VkPhysicalDeviceProperties::unpack($propsBlock);

    /*
     * Prefer a real GPU. On the Pi both V3D (INTEGRATED_GPU) and llvmpipe (CPU)
     * offer a graphics queue, and llvmpipe is usually enumerated second; taking
     * the first match would be a coin flip on enumeration order.
     */
    $integrated = VkPhysicalDeviceType::INTEGRATED_GPU->value;
    $better = $physicalDevice === 0
        || ($p->deviceType === $integrated && $preferredType !== $integrated);

    if ($better) {
        $physicalDevice = $candidate;
        $queueFamily = $family;
        $deviceProps = $p;
        $preferredType = $p->deviceType;
    }
}

$physicalDevice !== 0 || fail('no physical device has a graphics queue family');

$deviceVersion = ApiVersion::fromPacked($deviceProps->apiVersion);
printf(
    "device: %s api %d.%d.%d type %d queueFamily %d\n",
    $deviceProps->deviceName,
    $deviceVersion->major,
    $deviceVersion->minor,
    $deviceVersion->patch,
    $deviceProps->deviceType,
    $queueFamily
);

/* -- 3. Logical device, queue ----------------------------------------------- */

$priorities = $keep(Bridge::alloc(4), 'alloc queue priority');
Bridge::write($priorities, 0, pack('f', 1.0)) || fail('Bridge::write queue priority');

$queueInfo = $keep((new VkDeviceQueueCreateInfo(
    queueFamilyIndex: $queueFamily,
    queueCount: 1,
    pQueuePriorities: $priorities,
))->pack(), 'VkDeviceQueueCreateInfo::pack');

/*
 * Spec §9: a device that advertises VK_KHR_portability_subset must have it
 * enabled. That is every MoltenVK device. The empty layer name is how this
 * binding spells "no layer": a const char * parameter crosses as a PHP string
 * and cannot be null, and the loader treats "" as none.
 */
$deviceExtPtrs = [];
if ($isDarwin) {
    $ok(VK10::vkEnumerateDeviceExtensionProperties($physicalDevice, '', $countOut, 0), 'vkEnumerateDeviceExtensionProperties(count)');
    $extCount = $readCount();
    if ($extCount > 0) {
        $extList = $keep(Bridge::alloc($extCount * VkExtensionProperties::size()), 'alloc device extension list');
        $ok(VK10::vkEnumerateDeviceExtensionProperties($physicalDevice, '', $countOut, $extList), 'vkEnumerateDeviceExtensionProperties(list)');
        for ($i = 0; $i < $extCount; $i++) {
            $e = VkExtensionProperties::unpack($extList + $i * VkExtensionProperties::size());
            if ($e->extensionName === 'VK_KHR_portability_subset') {
                $deviceExtPtrs[] = $keep(Bridge::cstring('VK_KHR_portability_subset'), 'Bridge::cstring(VK_KHR_portability_subset)');
                break;
            }
        }
    }
}

$deviceExtArray = $keep(Bridge::alloc(max(1, count($deviceExtPtrs)) * 8), 'alloc device extension array');
foreach ($deviceExtPtrs as $i => $p) {
    $putHandle($deviceExtArray, $i, $p);
}

$deviceInfo = $keep((new VkDeviceCreateInfo(
    queueCreateInfoCount: 1,
    pQueueCreateInfos: $queueInfo,
    enabledExtensionCount: count($deviceExtPtrs),
    ppEnabledExtensionNames: $deviceExtArray,
))->pack(), 'VkDeviceCreateInfo::pack');

$deviceOut = $keep(Bridge::alloc(8), 'alloc device handle');
$ok(VK10::vkCreateDevice($physicalDevice, $deviceInfo, 0, $deviceOut), 'vkCreateDevice');
$device = $handleAt($deviceOut);
$device !== 0 || fail('vkCreateDevice succeeded but wrote a null handle');
Bridge::loadDevice($device) || fail('Bridge::loadDevice');

$queueOut = $keep(Bridge::alloc(8), 'alloc queue handle');
VK10::vkGetDeviceQueue($device, $queueFamily, 0, $queueOut);
$queue = $handleAt($queueOut);
$queue !== 0 || fail('vkGetDeviceQueue wrote a null handle');

/* -- 4. Colour image, its memory, its view ---------------------------------- */

$memPropsBlock = $keep(Bridge::alloc(VkPhysicalDeviceMemoryProperties::size()), 'alloc VkPhysicalDeviceMemoryProperties');
VK10::vkGetPhysicalDeviceMemoryProperties($physicalDevice, $memPropsBlock);
$memProps = VkPhysicalDeviceMemoryProperties::unpack($memPropsBlock);

/** First memory type allowed by memoryTypeBits that carries every required property. */
$memoryTypeIndex = static function (int $typeBits, int $required) use ($memProps): int {
    for ($i = 0; $i < $memProps->memoryTypeCount; $i++) {
        if (($typeBits & (1 << $i)) === 0) {
            continue;
        }
        // memoryTypes is a T[N] of by-value structs, so it is a list of value
        // objects — the same inline memory, read through its own type.
        if (($memProps->memoryTypes[$i]->propertyFlags & $required) === $required) {
            return $i;
        }
    }

    return -1;
};

$reqBlock = $keep(Bridge::alloc(VkMemoryRequirements::size()), 'alloc VkMemoryRequirements');

/** Allocate device memory for the requirements sitting in $reqBlock. */
$allocateFor = static function (int $required, string $what) use ($keep, $reqBlock, $memoryTypeIndex, $device, $ok, $handleAt): array {
    $req = VkMemoryRequirements::unpack($reqBlock);
    $type = $memoryTypeIndex($req->memoryTypeBits, $required);
    $type >= 0 || fail("{$what}: no memory type with flags {$required} in memoryTypeBits {$req->memoryTypeBits}");

    $info = $keep((new VkMemoryAllocateInfo(
        allocationSize: $req->size,
        memoryTypeIndex: $type,
    ))->pack(), "VkMemoryAllocateInfo::pack({$what})");

    $out = $keep(Bridge::alloc(8), "alloc memory handle ({$what})");
    $ok(VK10::vkAllocateMemory($device, $info, 0, $out), "vkAllocateMemory({$what})");
    $memory = $handleAt($out);
    $memory !== 0 || fail("vkAllocateMemory({$what}) wrote a null handle");

    return [$memory, $req->size];
};

$imageInfo = $keep((new VkImageCreateInfo(
    imageType: VkImageType::TYPE_2D,
    format: VkFormat::R8G8B8A8_UNORM,
    extent: new VkExtent3D(width: WIDTH, height: HEIGHT, depth: 1),
    mipLevels: 1,
    arrayLayers: 1,
    samples: VkSampleCountFlagBits::COUNT_1_BIT,
    tiling: VkImageTiling::OPTIMAL,
    usage: VkImageUsageFlagBits::COLOR_ATTACHMENT_BIT->value | VkImageUsageFlagBits::TRANSFER_SRC_BIT->value,
    sharingMode: VkSharingMode::EXCLUSIVE,
    initialLayout: VkImageLayout::UNDEFINED,
))->pack(), 'VkImageCreateInfo::pack');

$imageOut = $keep(Bridge::alloc(8), 'alloc image handle');
$ok(VK10::vkCreateImage($device, $imageInfo, 0, $imageOut), 'vkCreateImage');
$image = $handleAt($imageOut);

VK10::vkGetImageMemoryRequirements($device, $image, $reqBlock);
[$imageMemory] = $allocateFor(VkMemoryPropertyFlagBits::DEVICE_LOCAL_BIT->value, 'colour image');
$ok(VK10::vkBindImageMemory($device, $image, $imageMemory, 0), 'vkBindImageMemory');

$viewInfo = $keep((new VkImageViewCreateInfo(
    image: $image,
    viewType: VkImageViewType::TYPE_2D,
    format: VkFormat::R8G8B8A8_UNORM,
    components: new VkComponentMapping(
        r: VkComponentSwizzle::IDENTITY,
        g: VkComponentSwizzle::IDENTITY,
        b: VkComponentSwizzle::IDENTITY,
        a: VkComponentSwizzle::IDENTITY,
    ),
    subresourceRange: new VkImageSubresourceRange(
        aspectMask: VkImageAspectFlagBits::COLOR_BIT->value,
        baseMipLevel: 0,
        levelCount: 1,
        baseArrayLayer: 0,
        layerCount: 1,
    ),
))->pack(), 'VkImageViewCreateInfo::pack');

$viewOut = $keep(Bridge::alloc(8), 'alloc image view handle');
$ok(VK10::vkCreateImageView($device, $viewInfo, 0, $viewOut), 'vkCreateImageView');
$imageView = $handleAt($viewOut);

/* -- 5. Render pass and framebuffer ----------------------------------------- */

$attachments = $keep(Bridge::alloc(VkAttachmentDescription::size()), 'alloc VkAttachmentDescription[1]');
(new VkAttachmentDescription(
    format: VkFormat::R8G8B8A8_UNORM,
    samples: VkSampleCountFlagBits::COUNT_1_BIT,
    loadOp: VkAttachmentLoadOp::CLEAR,
    storeOp: VkAttachmentStoreOp::STORE,
    stencilLoadOp: VkAttachmentLoadOp::DONT_CARE,
    stencilStoreOp: VkAttachmentStoreOp::DONT_CARE,
    initialLayout: VkImageLayout::UNDEFINED,
    finalLayout: VkImageLayout::TRANSFER_SRC_OPTIMAL,
))->packInto($attachments);

$colorRefs = $keep(Bridge::alloc(VkAttachmentReference::size()), 'alloc VkAttachmentReference[1]');
(new VkAttachmentReference(
    attachment: 0,
    layout: VkImageLayout::COLOR_ATTACHMENT_OPTIMAL,
))->packInto($colorRefs);

$subpasses = $keep(Bridge::alloc(VkSubpassDescription::size()), 'alloc VkSubpassDescription[1]');
(new VkSubpassDescription(
    pipelineBindPoint: VkPipelineBindPoint::GRAPHICS,
    colorAttachmentCount: 1,
    pColorAttachments: $colorRefs,
))->packInto($subpasses);

/*
 * The colour write has to be finished and visible before vkCmdCopyImageToBuffer
 * reads the image. The implicit subpass-to-EXTERNAL dependency ends at
 * BOTTOM_OF_PIPE with an empty access mask, which makes the write available but
 * not visible to a transfer read, so the dependency is spelled out here.
 */
$dependencies = $keep(Bridge::alloc(VkSubpassDependency::size()), 'alloc VkSubpassDependency[1]');
(new VkSubpassDependency(
    srcSubpass: 0,
    dstSubpass: VK_SUBPASS_EXTERNAL,
    srcStageMask: VkPipelineStageFlagBits::COLOR_ATTACHMENT_OUTPUT_BIT->value,
    dstStageMask: VkPipelineStageFlagBits::TRANSFER_BIT->value,
    srcAccessMask: VkAccessFlagBits::COLOR_ATTACHMENT_WRITE_BIT->value,
    dstAccessMask: VkAccessFlagBits::TRANSFER_READ_BIT->value,
))->packInto($dependencies);

$renderPassInfo = $keep((new VkRenderPassCreateInfo(
    attachmentCount: 1,
    pAttachments: $attachments,
    subpassCount: 1,
    pSubpasses: $subpasses,
    dependencyCount: 1,
    pDependencies: $dependencies,
))->pack(), 'VkRenderPassCreateInfo::pack');

$renderPassOut = $keep(Bridge::alloc(8), 'alloc render pass handle');
$ok(VK10::vkCreateRenderPass($device, $renderPassInfo, 0, $renderPassOut), 'vkCreateRenderPass');
$renderPass = $handleAt($renderPassOut);

$fbAttachments = $keep(Bridge::alloc(8), 'alloc framebuffer attachment array');
$putHandle($fbAttachments, 0, $imageView);

$framebufferInfo = $keep((new VkFramebufferCreateInfo(
    renderPass: $renderPass,
    attachmentCount: 1,
    pAttachments: $fbAttachments,
    width: WIDTH,
    height: HEIGHT,
    layers: 1,
))->pack(), 'VkFramebufferCreateInfo::pack');

$framebufferOut = $keep(Bridge::alloc(8), 'alloc framebuffer handle');
$ok(VK10::vkCreateFramebuffer($device, $framebufferInfo, 0, $framebufferOut), 'vkCreateFramebuffer');
$framebuffer = $handleAt($framebufferOut);

/* -- 6. Shader modules from the vendored SPIR-V ----------------------------- */

/** Upload one .spv file and create its VkShaderModule. */
$shaderModule = static function (string $file) use ($keep, $device, $ok, $handleAt): int {
    $path = __DIR__ . '/shaders/' . $file;
    $code = @file_get_contents($path);
    is_string($code) && $code !== '' || fail("cannot read {$path}");
    strlen($code) % 4 === 0 || fail("{$file} is not a whole number of SPIR-V words");

    $bytes = $keep(Bridge::alloc(strlen($code)), "alloc {$file}");
    Bridge::write($bytes, 0, $code) || fail("Bridge::write {$file}");

    $info = $keep((new VkShaderModuleCreateInfo(
        codeSize: strlen($code),
        pCode: $bytes,
    ))->pack(), "VkShaderModuleCreateInfo::pack({$file})");

    $out = $keep(Bridge::alloc(8), "alloc shader module handle ({$file})");
    $ok(VK10::vkCreateShaderModule($device, $info, 0, $out), "vkCreateShaderModule({$file})");

    return $handleAt($out);
};

$vertModule = $shaderModule('triangle.vert.spv');
$fragModule = $shaderModule('triangle.frag.spv');

/* -- 7. Pipeline layout and graphics pipeline -------------------------------- */

$layoutInfo = $keep((new VkPipelineLayoutCreateInfo())->pack(), 'VkPipelineLayoutCreateInfo::pack');

$layoutOut = $keep(Bridge::alloc(8), 'alloc pipeline layout handle');
$ok(VK10::vkCreatePipelineLayout($device, $layoutInfo, 0, $layoutOut), 'vkCreatePipelineLayout');
$pipelineLayout = $handleAt($layoutOut);

$entryPoint = $keep(Bridge::cstring('main'), 'Bridge::cstring(main)');

$stages = $keep(Bridge::alloc(2 * VkPipelineShaderStageCreateInfo::size()), 'alloc VkPipelineShaderStageCreateInfo[2]');
(new VkPipelineShaderStageCreateInfo(
    stage: VkShaderStageFlagBits::VERTEX_BIT,
    module: $vertModule,
    pName: $entryPoint,
))->packInto($stages);
(new VkPipelineShaderStageCreateInfo(
    stage: VkShaderStageFlagBits::FRAGMENT_BIT,
    module: $fragModule,
    pName: $entryPoint,
))->packInto($stages + VkPipelineShaderStageCreateInfo::size());

$bindings = $keep(Bridge::alloc(VkVertexInputBindingDescription::size()), 'alloc VkVertexInputBindingDescription[1]');
(new VkVertexInputBindingDescription(
    binding: 0,
    stride: 8,                          // vec2 of 32-bit floats
    inputRate: VkVertexInputRate::VERTEX,
))->packInto($bindings);

$attributes = $keep(Bridge::alloc(VkVertexInputAttributeDescription::size()), 'alloc VkVertexInputAttributeDescription[1]');
(new VkVertexInputAttributeDescription(
    location: 0,
    binding: 0,
    format: VkFormat::R32G32_SFLOAT,
    offset: 0,
))->packInto($attributes);

$vertexInputState = $keep((new VkPipelineVertexInputStateCreateInfo(
    vertexBindingDescriptionCount: 1,
    pVertexBindingDescriptions: $bindings,
    vertexAttributeDescriptionCount: 1,
    pVertexAttributeDescriptions: $attributes,
))->pack(), 'VkPipelineVertexInputStateCreateInfo::pack');

$inputAssemblyState = $keep((new VkPipelineInputAssemblyStateCreateInfo(
    topology: VkPrimitiveTopology::TRIANGLE_LIST,
    primitiveRestartEnable: false,
))->pack(), 'VkPipelineInputAssemblyStateCreateInfo::pack');

$viewports = $keep(Bridge::alloc(VkViewport::size()), 'alloc VkViewport[1]');
(new VkViewport(
    x: 0.0,
    y: 0.0,
    width: (float) WIDTH,
    height: (float) HEIGHT,
    minDepth: 0.0,
    maxDepth: 1.0,
))->packInto($viewports);

$scissors = $keep(Bridge::alloc(VkRect2D::size()), 'alloc VkRect2D[1]');
(new VkRect2D(
    offset: new VkOffset2D(x: 0, y: 0),
    extent: new VkExtent2D(width: WIDTH, height: HEIGHT),
))->packInto($scissors);

$viewportState = $keep((new VkPipelineViewportStateCreateInfo(
    viewportCount: 1,
    pViewports: $viewports,
    scissorCount: 1,
    pScissors: $scissors,
))->pack(), 'VkPipelineViewportStateCreateInfo::pack');

$rasterizationState = $keep((new VkPipelineRasterizationStateCreateInfo(
    depthClampEnable: false,
    rasterizerDiscardEnable: false,
    polygonMode: VkPolygonMode::FILL,
    cullMode: VkCullModeFlagBits::NONE->value,
    frontFace: VkFrontFace::COUNTER_CLOCKWISE,
    depthBiasEnable: false,
    lineWidth: 1.0,                     // MoltenVK has no wide lines; 1.0 is always legal
))->pack(), 'VkPipelineRasterizationStateCreateInfo::pack');

$multisampleState = $keep((new VkPipelineMultisampleStateCreateInfo(
    rasterizationSamples: VkSampleCountFlagBits::COUNT_1_BIT,
    sampleShadingEnable: false,
    minSampleShading: 0.0,
    alphaToCoverageEnable: false,
    alphaToOneEnable: false,
))->pack(), 'VkPipelineMultisampleStateCreateInfo::pack');

$blendAttachments = $keep(Bridge::alloc(VkPipelineColorBlendAttachmentState::size()), 'alloc VkPipelineColorBlendAttachmentState[1]');
(new VkPipelineColorBlendAttachmentState(
    blendEnable: false,
    srcColorBlendFactor: VkBlendFactor::ONE,
    dstColorBlendFactor: VkBlendFactor::ZERO,
    colorBlendOp: VkBlendOp::ADD,
    srcAlphaBlendFactor: VkBlendFactor::ONE,
    dstAlphaBlendFactor: VkBlendFactor::ZERO,
    alphaBlendOp: VkBlendOp::ADD,
    // The proof's own VK_COLOR_COMPONENT_RGBA_BITS was always these four bits
    // OR'd. A mask is an int on both sides, so the ORing is done from ->value.
    colorWriteMask: VkColorComponentFlagBits::R_BIT->value
        | VkColorComponentFlagBits::G_BIT->value
        | VkColorComponentFlagBits::B_BIT->value
        | VkColorComponentFlagBits::A_BIT->value,
))->packInto($blendAttachments);

$colorBlendState = $keep((new VkPipelineColorBlendStateCreateInfo(
    logicOpEnable: false,
    logicOp: VkLogicOp::COPY,
    attachmentCount: 1,
    pAttachments: $blendAttachments,
    blendConstants: [0.0, 0.0, 0.0, 0.0],
))->pack(), 'VkPipelineColorBlendStateCreateInfo::pack');

$pipelineInfo = $keep((new VkGraphicsPipelineCreateInfo(
    stageCount: 2,
    pStages: $stages,
    pVertexInputState: $vertexInputState,
    pInputAssemblyState: $inputAssemblyState,
    pViewportState: $viewportState,
    pRasterizationState: $rasterizationState,
    pMultisampleState: $multisampleState,
    pColorBlendState: $colorBlendState,
    layout: $pipelineLayout,
    renderPass: $renderPass,
    subpass: 0,
    basePipelineHandle: 0,
    basePipelineIndex: -1,
))->pack(), 'VkGraphicsPipelineCreateInfo::pack');

$pipelineOut = $keep(Bridge::alloc(8), 'alloc pipeline handle');
$ok(VK10::vkCreateGraphicsPipelines($device, 0, 1, $pipelineInfo, 0, $pipelineOut), 'vkCreateGraphicsPipelines');
$pipeline = $handleAt($pipelineOut);
$pipeline !== 0 || fail('vkCreateGraphicsPipelines wrote a null handle');

/* -- 8. Vertex buffer, written through vkMapMemory's pointer ---------------- */

/*
 * Three clip-space vertices. Vulkan's Y axis points down, and cullMode is NONE,
 * so the winding does not matter; what matters is that the triangle covers the
 * centre of the image and none of its corners.
 */
$vertexData = pack('f*', -0.5, 0.5, 0.5, 0.5, 0.0, -0.5);
strlen($vertexData) === 24 || fail('vertex data is not 24 bytes');

$vertexBufferInfo = $keep((new VkBufferCreateInfo(
    size: strlen($vertexData),
    usage: VkBufferUsageFlagBits::VERTEX_BUFFER_BIT->value,
    sharingMode: VkSharingMode::EXCLUSIVE,
))->pack(), 'VkBufferCreateInfo::pack(vertex)');

$vertexBufferOut = $keep(Bridge::alloc(8), 'alloc vertex buffer handle');
$ok(VK10::vkCreateBuffer($device, $vertexBufferInfo, 0, $vertexBufferOut), 'vkCreateBuffer(vertex)');
$vertexBuffer = $handleAt($vertexBufferOut);

VK10::vkGetBufferMemoryRequirements($device, $vertexBuffer, $reqBlock);
[$vertexMemory] = $allocateFor(
    VkMemoryPropertyFlagBits::HOST_VISIBLE_BIT->value | VkMemoryPropertyFlagBits::HOST_COHERENT_BIT->value,
    'vertex buffer'
);

$mappedOut = $keep(Bridge::alloc(8), 'alloc mapped pointer');
$ok(VK10::vkMapMemory($device, $vertexMemory, 0, VK_WHOLE_SIZE, 0, $mappedOut), 'vkMapMemory(vertex)');
$mapped = $handleAt($mappedOut);
$mapped !== 0 || fail('vkMapMemory(vertex) wrote a null pointer');

/*
 * $mapped is the driver's memory, not a block this extension allocated, so
 * Bridge::write has no extent to bounds-check it against and writes it as
 * given — the price of the pointer rule.
 */
Bridge::write($mapped, 0, $vertexData) || fail('Bridge::write vertex data');
VK10::vkUnmapMemory($device, $vertexMemory);
$ok(VK10::vkBindBufferMemory($device, $vertexBuffer, $vertexMemory, 0), 'vkBindBufferMemory(vertex)');

/* -- 9. Host-visible readback buffer ---------------------------------------- */

$readBufferInfo = $keep((new VkBufferCreateInfo(
    size: IMAGE_BYTES,
    usage: VkBufferUsageFlagBits::TRANSFER_DST_BIT->value,
    sharingMode: VkSharingMode::EXCLUSIVE,
))->pack(), 'VkBufferCreateInfo::pack(readback)');

$readBufferOut = $keep(Bridge::alloc(8), 'alloc readback buffer handle');
$ok(VK10::vkCreateBuffer($device, $readBufferInfo, 0, $readBufferOut), 'vkCreateBuffer(readback)');
$readBuffer = $handleAt($readBufferOut);

VK10::vkGetBufferMemoryRequirements($device, $readBuffer, $reqBlock);
[$readMemory] = $allocateFor(
    VkMemoryPropertyFlagBits::HOST_VISIBLE_BIT->value | VkMemoryPropertyFlagBits::HOST_COHERENT_BIT->value,
    'readback buffer'
);
$ok(VK10::vkBindBufferMemory($device, $readBuffer, $readMemory, 0), 'vkBindBufferMemory(readback)');

/* -- 10. Command pool, command buffer, recording ---------------------------- */

$poolInfo = $keep((new VkCommandPoolCreateInfo(
    queueFamilyIndex: $queueFamily,
))->pack(), 'VkCommandPoolCreateInfo::pack');

$poolOut = $keep(Bridge::alloc(8), 'alloc command pool handle');
$ok(VK10::vkCreateCommandPool($device, $poolInfo, 0, $poolOut), 'vkCreateCommandPool');
$commandPool = $handleAt($poolOut);

$cmdAllocInfo = $keep((new VkCommandBufferAllocateInfo(
    commandPool: $commandPool,
    level: VkCommandBufferLevel::PRIMARY,
    commandBufferCount: 1,
))->pack(), 'VkCommandBufferAllocateInfo::pack');

$commandBuffers = $keep(Bridge::alloc(8), 'alloc command buffer array');
$ok(VK10::vkAllocateCommandBuffers($device, $cmdAllocInfo, $commandBuffers), 'vkAllocateCommandBuffers');
$commandBuffer = $handleAt($commandBuffers);
$commandBuffer !== 0 || fail('vkAllocateCommandBuffers wrote a null handle');

$beginInfo = $keep((new VkCommandBufferBeginInfo(
    flags: VkCommandBufferUsageFlagBits::ONE_TIME_SUBMIT_BIT->value,
))->pack(), 'VkCommandBufferBeginInfo::pack');

$clearValues = $keep((new VkClearValue(
    color: new VkClearColorValue(float32: [0.0, 0.0, 0.0, 1.0]),
))->pack(), 'VkClearValue::pack');

$renderPassBegin = $keep((new VkRenderPassBeginInfo(
    renderPass: $renderPass,
    framebuffer: $framebuffer,
    renderArea: new VkRect2D(
        offset: new VkOffset2D(x: 0, y: 0),
        extent: new VkExtent2D(width: WIDTH, height: HEIGHT),
    ),
    clearValueCount: 1,
    pClearValues: $clearValues,
))->pack(), 'VkRenderPassBeginInfo::pack');

$vertexBuffers = $keep(Bridge::alloc(8), 'alloc vertex buffer array');
$putHandle($vertexBuffers, 0, $vertexBuffer);
$vertexOffsets = $keep(Bridge::alloc(8), 'alloc vertex offset array');   // one VkDeviceSize, zero

$regions = $keep((new VkBufferImageCopy(
    bufferOffset: 0,
    bufferRowLength: 0,                 // 0 = tightly packed, WIDTH texels per row
    bufferImageHeight: 0,
    imageSubresource: new VkImageSubresourceLayers(
        aspectMask: VkImageAspectFlagBits::COLOR_BIT->value,
        mipLevel: 0,
        baseArrayLayer: 0,
        layerCount: 1,
    ),
    imageOffset: new VkOffset3D(x: 0, y: 0, z: 0),
    imageExtent: new VkExtent3D(width: WIDTH, height: HEIGHT, depth: 1),
))->pack(), 'VkBufferImageCopy::pack');

$ok(VK10::vkBeginCommandBuffer($commandBuffer, $beginInfo), 'vkBeginCommandBuffer');
VK10::vkCmdBeginRenderPass($commandBuffer, $renderPassBegin, VkSubpassContents::INLINE);
VK10::vkCmdBindPipeline($commandBuffer, VkPipelineBindPoint::GRAPHICS, $pipeline);
VK10::vkCmdBindVertexBuffers($commandBuffer, 0, 1, $vertexBuffers, $vertexOffsets);
VK10::vkCmdDraw($commandBuffer, 3, 1, 0, 0);
VK10::vkCmdEndRenderPass($commandBuffer);
VK10::vkCmdCopyImageToBuffer($commandBuffer, $image, VkImageLayout::TRANSFER_SRC_OPTIMAL, $readBuffer, 1, $regions);
$ok(VK10::vkEndCommandBuffer($commandBuffer), 'vkEndCommandBuffer');

/* -- 11. Submit and wait ---------------------------------------------------- */

$submitInfo = $keep((new VkSubmitInfo(
    commandBufferCount: 1,
    pCommandBuffers: $commandBuffers,
))->pack(), 'VkSubmitInfo::pack');

$ok(VK10::vkQueueSubmit($queue, 1, $submitInfo, 0), 'vkQueueSubmit');
$ok(VK10::vkQueueWaitIdle($queue), 'vkQueueWaitIdle');

/* -- 12. Read the pixels back ----------------------------------------------- */

$ok(VK10::vkMapMemory($device, $readMemory, 0, VK_WHOLE_SIZE, 0, $mappedOut), 'vkMapMemory(readback)');
$readPtr = $handleAt($mappedOut);
$readPtr !== 0 || fail('vkMapMemory(readback) wrote a null pointer');

$pixels = (string) Bridge::read($readPtr, 0, IMAGE_BYTES);
strlen($pixels) === IMAGE_BYTES || fail('Bridge::read returned ' . strlen($pixels) . ' bytes, wanted ' . IMAGE_BYTES);

$pixelAt = static function (string $bytes, int $x, int $y): array {
    $o = ($y * WIDTH + $x) * PIXEL_BYTES;

    return [ord($bytes[$o]), ord($bytes[$o + 1]), ord($bytes[$o + 2]), ord($bytes[$o + 3])];
};

$centre = $pixelAt($pixels, 32, 32);
$corner = $pixelAt($pixels, 0, 0);

printf("pixel(32,32): %d,%d,%d,%d\n", ...$centre);
printf("pixel(0,0): %d,%d,%d,%d\n", ...$corner);

VK10::vkUnmapMemory($device, $readMemory);

/*
 * (1.0, 0.5, 0.25, 1.0) through an R8G8B8A8_UNORM attachment is 255, 127.5, 63.75,
 * 255; the rounding direction is the implementation's, so each channel is allowed
 * one unit of slack. The clear colour is exact.
 */
$wantCentre = [255, 128, 64, 255];
foreach ($wantCentre as $c => $want) {
    if (abs($centre[$c] - $want) > 1) {
        fail('pixel(32,32) is ' . implode(',', $centre) . ', wanted ' . implode(',', $wantCentre) . ' +/-1 per channel');
    }
}
if ($corner !== [0, 0, 0, 255]) {
    fail('pixel(0,0) is ' . implode(',', $corner) . ', wanted 0,0,0,255');
}

/* -- 13. Tear everything down in reverse ------------------------------------ */

VK10::vkDestroyCommandPool($device, $commandPool, 0);       // frees its command buffers
VK10::vkDestroyBuffer($device, $readBuffer, 0);
VK10::vkFreeMemory($device, $readMemory, 0);
VK10::vkDestroyBuffer($device, $vertexBuffer, 0);
VK10::vkFreeMemory($device, $vertexMemory, 0);
VK10::vkDestroyPipeline($device, $pipeline, 0);
VK10::vkDestroyPipelineLayout($device, $pipelineLayout, 0);
VK10::vkDestroyShaderModule($device, $fragModule, 0);
VK10::vkDestroyShaderModule($device, $vertModule, 0);
VK10::vkDestroyFramebuffer($device, $framebuffer, 0);
VK10::vkDestroyRenderPass($device, $renderPass, 0);
VK10::vkDestroyImageView($device, $imageView, 0);
VK10::vkDestroyImage($device, $image, 0);
VK10::vkFreeMemory($device, $imageMemory, 0);
VK10::vkDestroyDevice($device, 0);
VK10::vkDestroyInstance($instance, 0);

foreach (array_reverse($blocks) as $b) {
    Bridge::free($b);
}
$blocks = [];

echo "PROOF_HEADLESS_TYPED_OK\n";
