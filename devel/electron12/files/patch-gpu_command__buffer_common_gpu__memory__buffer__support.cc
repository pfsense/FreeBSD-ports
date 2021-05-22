--- gpu/command_buffer/common/gpu_memory_buffer_support.cc.orig	2021-01-07 00:36:35 UTC
+++ gpu/command_buffer/common/gpu_memory_buffer_support.cc
@@ -55,7 +55,7 @@ bool IsImageSizeValidForGpuMemoryBufferFormat(const gf
 uint32_t GetPlatformSpecificTextureTarget() {
 #if defined(OS_MAC)
   return macos_specific_texture_target;
-#elif defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#elif defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   return GL_TEXTURE_EXTERNAL_OES;
 #elif defined(OS_WIN) || defined(OS_FUCHSIA)
   return GL_TEXTURE_2D;
@@ -85,7 +85,7 @@ GPU_EXPORT uint32_t GetBufferTextureTarget(gfx::Buffer
 
 GPU_EXPORT bool NativeBufferNeedsPlatformSpecificTextureTarget(
     gfx::BufferFormat format) {
-#if defined(USE_OZONE) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(USE_OZONE) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   // Always use GL_TEXTURE_2D as the target for RGB textures.
   // https://crbug.com/916728
   if (format == gfx::BufferFormat::R_8 || format == gfx::BufferFormat::RG_88 ||
