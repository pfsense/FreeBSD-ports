--- src/3rdparty/chromium/gpu/command_buffer/service/shared_image/angle_vulkan_image_backing_factory.cc.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/gpu/command_buffer/service/shared_image/angle_vulkan_image_backing_factory.cc
@@ -94,7 +94,7 @@ bool AngleVulkanImageBackingFactory::CanUseAngleVulkan
   // TODO(penghuang): verify the scanout is the right usage for video playback.
   // crbug.com/1280798
   constexpr auto kSupportedUsages =
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
       SHARED_IMAGE_USAGE_SCANOUT |
 #endif
       SHARED_IMAGE_USAGE_GLES2 | SHARED_IMAGE_USAGE_GLES2_FRAMEBUFFER_HINT |
