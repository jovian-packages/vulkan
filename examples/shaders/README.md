# examples/shaders — the vendored SPIR-V for the headless proof

> **Vendored from ext-vulkan, byte for byte.** These five files are a copy of
> `php-io-extensions/vulkan/vulkan/examples/shaders/`, because
> `examples/proof_headless_typed.php` is the extension's own proof with types
> added and it has to draw the *same* triangle for the pixel comparison in
> `scripts/gates/verify-smoke.mjs` to mean anything. Do not recompile them
> here: change them in the extension, where the recorded command below lives,
> and copy the result across.

## The files

Two trivial shaders and their compiled SPIR-V. The `.spv` files are
**committed**, so neither box needs a GLSL compiler to run the proof: Vulkan
consumes SPIR-V, and SPIR-V is portable across drivers and architectures.
Only this directory's `.spv` files are regenerated, and only by hand, on the
Mac.

## The sources

`triangle.vert` takes one `vec2` at location 0 and passes it straight through
as a clip-space position. `triangle.frag` writes the constant colour
`(1.0, 0.5, 0.25, 1.0)` to location 0 — which is `255, 128, 64, 255` after the
`VK_FORMAT_R8G8B8A8_UNORM` attachment rounds it, and that is the byte value
`proof_headless.php` checks at pixel (32, 32).

There is no geometry shader, no triangle fan and no wide line anywhere here,
because MoltenVK implements the Vulkan portability subset and has none of the
three (see `.okf/traps/moltenvk-portability.md`). A triangle list of three
vertices needs none of them.

## The command, run once on the Mac

```bash
glslangValidator -V examples/shaders/triangle.vert -o examples/shaders/triangle.vert.spv
glslangValidator -V examples/shaders/triangle.frag -o examples/shaders/triangle.frag.spv
```

`glslangValidator` came from Homebrew `glslang` 16.5.0 at
`/opt/homebrew/bin/glslangValidator`. `glslangValidator --version` printed:

```
Glslang Version: 11:16.5.0
ESSL Version: OpenGL ES GLSL 3.20 glslang Khronos. 16.5.0
GLSL Version: 4.60 glslang Khronos. 16.5.0
SPIR-V Version 0x00010600, Revision 1
GLSL.std.450 Version 100, Revision 1
Khronos Tool ID 8
SPIR-V Generator Version 11
GL_KHR_vulkan_glsl version 100
ARB_GL_gl_spirv version 100
```

## What was produced

| file | bytes | SPIR-V version word | sha256 |
|---|---:|---|---|
| `triangle.vert.spv` | 820 | `1.0` | `7fe1fb776c9f39c28e1f51f5406170c9501738fdf0b0a05220872aec43e9a2aa` |
| `triangle.frag.spv` | 368 | `1.0` | `c2f756331d67054258968af24c48efd8e444147adf3982242b20946cdb38cdea` |

`-V` alone targets Vulkan 1.0 semantics, so the module header says SPIR-V
**1.0** — the version every Vulkan implementation from 1.0 onward must accept.
That is deliberate: the Pi's V3D device reports Vulkan 1.3 and MoltenVK reports
1.4, and neither has to care. (`--target-env vulkan1.3` would emit SPIR-V 1.6
and would not load on a 1.0 or 1.1 device.)

Both files begin with the SPIR-V magic word `0x07230203` little-endian, which
is what `vkCreateShaderModule` looks for; the proof passes the bytes through
`Bridge::alloc` + `Bridge::write` untouched, so the file's endianness is the
file's own business.

## Regenerating

Only on the Mac, only with the two commands above, and only alongside an
update to the table here. `glslang` is **not** a build dependency of the
extension and is not required on the Pi.
