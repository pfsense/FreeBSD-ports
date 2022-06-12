--- base/base_switches.h.orig	2022-05-11 07:16:46 UTC
+++ base/base_switches.h
@@ -60,7 +60,7 @@ extern const char kEnableIdleTracing[];
 extern const char kForceFieldTrialParams[];
 #endif
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 extern const char kEnableThreadInstructionCount[];
 
 // TODO(crbug.com/1176772): Remove kEnableCrashpad and IsCrashpadEnabled() when
