--- content/browser/browser_child_process_host_impl.cc.orig	2023-07-21 09:49:17 UTC
+++ content/browser/browser_child_process_host_impl.cc
@@ -321,6 +321,7 @@ void BrowserChildProcessHostImpl::LaunchWithoutExtraCo
       switches::kDisableBestEffortTasks,
       switches::kDisableLogging,
       switches::kEnableLogging,
+      switches::kDisableUnveil,
       switches::kIPCConnectionTimeout,
       switches::kLogBestEffortTasks,
       switches::kLogFile,
