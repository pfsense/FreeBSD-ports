--- base/process/launch_posix.cc.orig	2025-04-04 08:52:13 UTC
+++ base/process/launch_posix.cc
@@ -62,6 +62,9 @@
 #error "macOS should use launch_mac.cc"
 #endif
 
+#if defined(OS_FREEBSD)
+#pragma weak environ
+#endif
 extern char** environ;
 
 namespace base {
