--- demo3.c.orig	2012-06-12 02:35:49 UTC
+++ demo3.c
@@ -37,17 +37,17 @@
 #include <locale.h>
 #include <setjmp.h>
 
-#ifdef HAVE_SELECT
-#ifdef HAVE_SYS_SELECT_H
-#include <sys/select.h>
-#endif
-#endif
-
 #include <unistd.h>
 #include <sys/stat.h>
 #include <sys/time.h>    
 #include <sys/types.h>
 #include <signal.h>
+
+#ifdef HAVE_SELECT
+#ifdef HAVE_SYS_SELECT_H
+#include <sys/select.h>
+#endif
+#endif
 
 #include "libtecla.h"
 
