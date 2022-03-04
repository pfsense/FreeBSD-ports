--- src/3rdparty/chromium/content/browser/scheduler/responsiveness/jank_monitor.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/content/browser/scheduler/responsiveness/jank_monitor.cc
@@ -298,7 +298,7 @@ void JankMonitor::ThreadExecutionState::DidRunTaskOrEv
     // in context menus, among others). Simply ignore the mismatches for now.
     // See https://crbug.com/929813 for the details of why the mismatch
     // happens.
-#if !defined(OS_CHROMEOS) && defined(OS_LINUX) && defined(USE_OZONE)
+#if !defined(OS_CHROMEOS) && (defined(OS_LINUX) || defined(OS_BSD)) && defined(USE_OZONE)
     task_execution_metadata_.clear();
 #endif
     return;
