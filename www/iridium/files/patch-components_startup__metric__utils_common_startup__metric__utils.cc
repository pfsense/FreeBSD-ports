--- components/startup_metric_utils/common/startup_metric_utils.cc.orig	2025-09-11 13:19:19 UTC
+++ components/startup_metric_utils/common/startup_metric_utils.cc
@@ -95,7 +95,7 @@ base::TimeTicks CommonStartupMetricRecorder::StartupTi
   // Enabling this logic on OS X causes a significant performance regression.
   // TODO(crbug.com/40464036): Remove IS_APPLE ifdef once utility processes
   // set their desired main thread priority.
-#if !BUILDFLAG(IS_APPLE)
+#if !BUILDFLAG(IS_APPLE) && !BUILDFLAG(IS_BSD)
   static bool statics_initialized = false;
   if (!statics_initialized) {
     statics_initialized = true;
