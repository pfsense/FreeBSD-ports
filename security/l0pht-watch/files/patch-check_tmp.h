--- check_tmp.h.orig	1998-12-24 15:40:13 UTC
+++ check_tmp.h
@@ -1,16 +1,23 @@
 #ifndef __CHECK_TMP_H
 #define __CHECK_TMP_H
 
-#include <stdlib.h>
-#include <stdio.h>
 #include <sys/types.h>
+#include <sys/mman.h>
 #include <sys/stat.h>
+#include <sys/param.h>
+#include <sys/ttycom.h>
 #include <dirent.h>
-#include <pwd.h>
-#include <grp.h>
 #include <fcntl.h>
-#include <sys/mman.h>
+#include <grp.h>
+#include <pwd.h>
+#include <stdlib.h>
+#include <stdio.h>
 #include <unistd.h>
+#if (__FreeBSD_version >= 500001) || (__FreeBSD_version >= 400019 && __FreeBSD_version < 500000)
+#include <sys/event.h>
+#include <sys/time.h>
+#include <err.h>
+#endif
 
 #if defined (REGEXPR_3G)
 #include <regexpr.h>
@@ -34,7 +41,7 @@ struct listStruct {
   struct listStruct *next;
 };  
 
-int syslogflag; /* grody after thought */
+extern int syslogflag; /* grody after thought */
 
 /* things to shut lint up */
 char *strrchr(const char *s, int c);
