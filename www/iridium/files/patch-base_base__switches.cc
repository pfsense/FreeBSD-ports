--- base/base_switches.cc.orig	2022-03-28 18:11:04 UTC
+++ base/base_switches.cc
@@ -170,7 +170,7 @@ const char kForceFieldTrialParams[] = "force-fieldtria
 
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // Controls whether or not retired instruction counts are surfaced for threads
 // in trace events on Linux.
 //
