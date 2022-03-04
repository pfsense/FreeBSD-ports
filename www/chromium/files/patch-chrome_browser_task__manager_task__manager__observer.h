--- chrome/browser/task_manager/task_manager_observer.h.orig	2021-07-19 18:45:09 UTC
+++ chrome/browser/task_manager/task_manager_observer.h
@@ -46,11 +46,11 @@ enum RefreshType {
   // or backgrounded.
   REFRESH_TYPE_PRIORITY = 1 << 13,
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC) || defined(OS_BSD)
   // For observers interested in getting the number of open file descriptors of
   // processes.
   REFRESH_TYPE_FD_COUNT = 1 << 14,
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC) || defined(OS_BSD)
 
   REFRESH_TYPE_KEEPALIVE_COUNT = 1 << 15,
   REFRESH_TYPE_MEMORY_FOOTPRINT = 1 << 16,
