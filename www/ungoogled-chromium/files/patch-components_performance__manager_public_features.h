--- components/performance_manager/public/features.h.orig	2025-05-06 12:23:00 UTC
+++ components/performance_manager/public/features.h
@@ -19,7 +19,7 @@ namespace performance_manager::features {
 
 #if !BUILDFLAG(IS_ANDROID)
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #define URGENT_DISCARDING_FROM_PERFORMANCE_MANAGER() false
 #else
 #define URGENT_DISCARDING_FROM_PERFORMANCE_MANAGER() true
