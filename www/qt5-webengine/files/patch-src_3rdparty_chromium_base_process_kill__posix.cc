--- src/3rdparty/chromium/base/process/kill_posix.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/base/process/kill_posix.cc
@@ -160,7 +160,7 @@ void EnsureProcessTerminated(Process process) {
       0, new BackgroundReaper(std::move(process), TimeDelta::FromSeconds(2)));
 }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 void EnsureProcessGetsReaped(Process process) {
   DCHECK(!process.is_current());
 
@@ -171,7 +171,7 @@ void EnsureProcessGetsReaped(Process process) {
   PlatformThread::CreateNonJoinable(
       0, new BackgroundReaper(std::move(process), TimeDelta()));
 }
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 #endif  // !defined(OS_APPLE)
 #endif  // !defined(OS_NACL_NONSFI)
