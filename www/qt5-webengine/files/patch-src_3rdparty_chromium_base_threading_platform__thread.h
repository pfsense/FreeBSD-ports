--- src/3rdparty/chromium/base/threading/platform_thread.h.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/base/threading/platform_thread.h
@@ -230,7 +230,7 @@ class BASE_EXPORT PlatformThread {
   // Returns a realtime period provided by |delegate|.
   static TimeDelta GetRealtimePeriod(Delegate* delegate);
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   // Toggles a specific thread's priority at runtime. This can be used to
   // change the priority of a thread in a different process and will fail
   // if the calling process does not have proper permissions. The
