--- libpkg/lua_scripts.c.orig	2026-03-02 19:14:34 UTC
+++ libpkg/lua_scripts.c
@@ -4,8 +4,10 @@
  * SPDX-License-Identifier: BSD-2-Clause
  */
 
+#if NETGATE_NOT_YET
 #if __has_include(<sys/procctl.h>)
 #include <sys/procctl.h>
+#endif
 #endif
 
 #include <sys/types.h>
