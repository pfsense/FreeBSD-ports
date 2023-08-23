--- components/performance_manager/public/features.h.orig	2023-07-24 14:27:53 UTC
+++ components/performance_manager/public/features.h
@@ -28,7 +28,7 @@ BASE_DECLARE_FEATURE(kRunOnDedicatedThreadPoolThread);
 
 #if !BUILDFLAG(IS_ANDROID)
 
-#if BUILDFLAG(IS_CHROMEOS_ASH) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_CHROMEOS_ASH) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #define URGENT_DISCARDING_FROM_PERFORMANCE_MANAGER() false
 #else
 #define URGENT_DISCARDING_FROM_PERFORMANCE_MANAGER() true
