--- process.c.orig	2010-05-13 19:14:00.000000000 +0000
+++ process.c	2010-05-13 19:14:05.000000000 +0000
@@ -18,6 +18,8 @@
 */
 #ifdef STREAM_ANALYZER
 #include <sys/types.h>
+#include <stdio.h>
+#include <stdlib.h>
 #include <string.h>
 #include <sys/time.h>
 #include <sys/timeb.h>
