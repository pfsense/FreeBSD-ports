--- src/3rdparty/chromium/base/files/file_path_watcher.cc.orig	2020-11-07 01:22:36 UTC
+++ src/3rdparty/chromium/base/files/file_path_watcher.cc
@@ -20,10 +20,10 @@ FilePathWatcher::~FilePathWatcher() {
 // static
 bool FilePathWatcher::RecursiveWatchAvailable() {
 #if (defined(OS_MACOSX) && !defined(OS_IOS)) || defined(OS_WIN) || \
-    defined(OS_LINUX) || defined(OS_ANDROID) || defined(OS_AIX)
+    (defined(OS_LINUX) && !defined(OS_BSD)) || defined(OS_ANDROID) || defined(OS_AIX)
   return true;
 #else
-  // FSEvents isn't available on iOS.
+  // FSEvents isn't available on iOS and the kqueue watcher.
   return false;
 #endif
 }
