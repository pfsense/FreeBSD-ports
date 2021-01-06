--- bootloader/src/main.c.orig	2019-07-09 19:14:04 UTC
+++ bootloader/src/main.c
@@ -16,6 +16,11 @@
  * main: For OS X and Linux
  */
 
+#ifdef __FreeBSD__
+    #include <sys/cdefs.h>
+    #include <floatingpoint.h>
+    #include <ieeefp.h>
+#endif
 #ifdef _WIN32
     #include <windows.h>
     #include <wchar.h>
@@ -33,9 +38,6 @@
 #include "pyi_global.h"
 #include "pyi_win32_utils.h"
 
-#ifdef __FreeBSD__
-    #include <floatingpoint.h>
-#endif
 
 #if defined(_WIN32)
     #define MS_WINDOWS
@@ -87,19 +89,12 @@ main(int argc, char **argv)
 {
     int res;
 
-    #ifdef __FreeBSD__
-    fp_except_t m;
-    #endif
 
     /* 754 requires that FP exceptions run in "no stop" mode by default,
      * and until C vendors implement C99's ways to control FP exceptions,
      * Python requires non-stop mode.  Alas, some platforms enable FP
      * exceptions by default.  Here we disable them.
      */
-    #ifdef __FreeBSD__
-    m = fpgetmask();
-    fpsetmask(m & ~FP_X_OFL);
-    #endif
 
     res = pyi_main(argc, argv);
     return res;
