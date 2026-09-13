<?php

declare(strict_types=1);

namespace Jovian\Bindings\Vulkan\Values;

/**
 * A packed Vulkan API version, unpacked.
 *
 * Vulkan spells a version as one `uint32` — `VK_MAKE_API_VERSION(variant,
 * major, minor, patch)` — and every place it appears, `Bridge::version()`,
 * `VkApplicationInfo.apiVersion`, `VkPhysicalDeviceProperties.apiVersion`,
 * hands over that same `uint32`. This is that number as a readonly value
 * object, on `jovian/metal`'s `MTLSize` shape and `jovian/ogx`'s
 * `ContextVersion` precedent.
 *
 * Everything here is arithmetic on a number the extension already returned.
 * Nothing is fetched, computed from the driver, or cached; `fromPacked()` is
 * a constructor and `toPacked()` is its inverse, exactly the way
 * `Structs\*::fromArray()` and `toArray()` are for a struct.
 *
 * `0.0.0` is the honest answer when the loader could not say — a 1.0 loader
 * has no `vkEnumerateInstanceVersion` and `Bridge::version()` returns `0`. It
 * is not an error and it does not throw: ext-vulkan has no error side
 * channel, and neither does this.
 */
final readonly class ApiVersion
{
    public function __construct(
        public int $major = 0,
        public int $minor = 0,
        public int $patch = 0,
        public int $variant = 0,
    ) {}

    /**
     * The constructor under the name the C macro uses.
     *
     * `VK_MAKE_API_VERSION(0, 1, 3, 0)` is `ApiVersion::make(1, 3, 0)`; the
     * variant is last here because it is `0` for every Vulkan anyone runs,
     * and putting it first would make the common call read wrong.
     */
    public static function make(int $major, int $minor, int $patch = 0, int $variant = 0): self
    {
        return new self($major, $minor, $patch, $variant);
    }

    /** The `uint32` layout: `variant << 29 | major << 22 | minor << 12 | patch`. */
    public static function fromPacked(int $packed): self
    {
        return new self(
            major: ($packed >> 22) & 0x7F,
            minor: ($packed >> 12) & 0x3FF,
            patch: $packed & 0xFFF,
            variant: ($packed >> 29) & 0x7,
        );
    }

    public function toPacked(): int
    {
        return ($this->variant << 29) | ($this->major << 22) | ($this->minor << 12) | $this->patch;
    }

    /**
     * True when this version is at least `$major.$minor.$patch`.
     *
     * The variant is deliberately not compared. A non-zero variant is a
     * *different* API with its own numbering, so "newer" is not a question
     * that has an answer across variants; read `$version->variant` when you
     * need to know which API you are holding.
     */
    public function atLeast(int $major, int $minor = 0, int $patch = 0): bool
    {
        return [$this->major, $this->minor, $this->patch] >= [$major, $minor, $patch];
    }

    public function __toString(): string
    {
        $text = $this->major . '.' . $this->minor . '.' . $this->patch;

        return $this->variant === 0 ? $text : $text . ' (variant ' . $this->variant . ')';
    }
}
