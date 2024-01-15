--- third_party/vulkan-deps/vulkan-validation-layers/src/layers/vulkan/generated/vk_extension_helper.h.orig	2023-08-10 01:51:21 UTC
+++ third_party/vulkan-deps/vulkan-validation-layers/src/layers/vulkan/generated/vk_extension_helper.h
@@ -88,9 +88,9 @@ class APIVersion {
 
     bool valid() const { return api_version_ != VVL_UNRECOGNIZED_API_VERSION; }
     uint32_t value() const { return api_version_; }
-    uint32_t major() const { return VK_API_VERSION_MAJOR(api_version_); }
-    uint32_t minor() const { return VK_API_VERSION_MINOR(api_version_); }
-    uint32_t patch() const { return VK_API_VERSION_PATCH(api_version_); }
+    uint32_t vk_major() const { return VK_API_VERSION_MAJOR(api_version_); }
+    uint32_t vk_minor() const { return VK_API_VERSION_MINOR(api_version_); }
+    uint32_t vk_patch() const { return VK_API_VERSION_PATCH(api_version_); }
 
     bool operator<(APIVersion api_version) const { return api_version_ < api_version.api_version_; }
     bool operator<=(APIVersion api_version) const { return api_version_ <= api_version.api_version_; }
