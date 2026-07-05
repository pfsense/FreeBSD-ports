--- libpkg/scripts.c.orig	2026-03-02 19:14:19 UTC
+++ libpkg/scripts.c
@@ -27,8 +27,10 @@
  */
 
 #include <sys/wait.h>
+#if NETGATE_NOT_YET
 #if __has_include(<sys/procctl.h>)
 #include <sys/procctl.h>
+#endif
 #endif
 
 #include <assert.h>
