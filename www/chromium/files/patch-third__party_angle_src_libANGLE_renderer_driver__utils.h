--- third_party/angle/src/libANGLE/renderer/driver_utils.h.orig	2023-07-16 15:47:57 UTC
+++ third_party/angle/src/libANGLE/renderer/driver_utils.h
@@ -212,7 +212,7 @@ inline bool IsWindows()
 
 inline bool IsLinux()
 {
-#if defined(ANGLE_PLATFORM_LINUX)
+#if defined(ANGLE_PLATFORM_LINUX) || defined(ANGLE_PLATFORM_BSD)
     return true;
 #else
     return false;
