--- base/process/kill_posix.cc.orig	2022-05-11 07:16:46 UTC
+++ base/process/kill_posix.cc
@@ -159,7 +159,7 @@ void EnsureProcessTerminated(Process process) {
       0, new BackgroundReaper(std::move(process), Seconds(2)));
 }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 void EnsureProcessGetsReaped(Process process) {
   DCHECK(!process.is_current());
 
