--- base/base_switches.cc.orig	2022-05-11 07:16:46 UTC
+++ base/base_switches.cc
@@ -170,7 +170,7 @@ const char kForceFieldTrialParams[] = "force-fieldtria
 
 #endif
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 // Controls whether or not retired instruction counts are surfaced for threads
 // in trace events on Linux.
 //
