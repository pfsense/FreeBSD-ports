--- src/3rdparty/chromium/base/debug/stack_trace.h.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/base/debug/stack_trace.h
@@ -19,6 +19,7 @@
 #if !defined(OS_NACL)
 #include <signal.h>
 #endif
+#include <sys/stdint.h>
 #include <unistd.h>
 #endif
 
