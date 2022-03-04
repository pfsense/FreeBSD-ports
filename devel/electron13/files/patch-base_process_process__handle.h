--- base/process/process_handle.h.orig	2021-01-07 00:36:18 UTC
+++ base/process/process_handle.h
@@ -103,7 +103,7 @@ BASE_EXPORT ProcessId GetCurrentProcId();
 // processes may be reused.
 BASE_EXPORT UniqueProcId GetUniqueIdForProcess();
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 // When a process is started in a different PID namespace from the browser
 // process, this function must be called with the process's PID in the browser's
 // PID namespace in order to initialize its unique ID. Not thread safe.
