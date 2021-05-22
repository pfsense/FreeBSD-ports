--- third_party/libXNVCtrl/NVCtrl.c.orig	2021-01-07 00:37:22 UTC
+++ third_party/libXNVCtrl/NVCtrl.c
@@ -27,10 +27,6 @@
  * libXNVCtrl library properly protects the Display connection.
  */
 
-#if !defined(XTHREADS)
-#define XTHREADS
-#endif /* XTHREADS */
-
 #define NEED_EVENTS
 #define NEED_REPLIES
 #include <stdint.h>
@@ -39,6 +35,11 @@
 #include <X11/Xutil.h>
 #include <X11/extensions/Xext.h>
 #include <X11/extensions/extutil.h>
+
+#if !defined(XTHREADS)
+#define XTHREADS
+#endif /* XTHREADS */
+
 #include "NVCtrlLib.h"
 #include "nv_control.h"
 
