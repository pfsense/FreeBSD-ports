--- libs/bluevk/include/bluevk/BlueVK.h.orig	2022-11-11 07:03:46 UTC
+++ libs/bluevk/include/bluevk/BlueVK.h
@@ -42,7 +42,7 @@
     #define VK_USE_PLATFORM_ANDROID_KHR 1
     #elif defined(IOS)
     #define VK_USE_PLATFORM_IOS_MVK 1
-    #elif defined(__linux__)
+    #elif defined(__linux__) || defined(__FreeBSD__)
     #if defined(FILAMENT_SUPPORTS_XCB)
     #define VK_USE_PLATFORM_XCB_KHR 1
     #endif
