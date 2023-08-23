--- gpu/vulkan/vulkan_function_pointers.cc.orig	2023-07-24 14:27:53 UTC
+++ gpu/vulkan/vulkan_function_pointers.cc
@@ -1151,7 +1151,7 @@ bool VulkanFunctionPointers::BindDeviceFunctionPointer
     }
   }
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   if (gfx::HasExtension(enabled_extensions,
                         VK_EXT_IMAGE_DRM_FORMAT_MODIFIER_EXTENSION_NAME)) {
     vkGetImageDrmFormatModifierPropertiesEXT =
@@ -1339,7 +1339,7 @@ void VulkanFunctionPointers::ResetForTesting() {
   vkGetSwapchainImagesKHR = nullptr;
   vkQueuePresentKHR = nullptr;
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   vkGetImageDrmFormatModifierPropertiesEXT = nullptr;
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
 }
