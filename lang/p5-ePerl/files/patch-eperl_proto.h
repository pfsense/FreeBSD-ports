--- eperl_proto.h.orig	1998-07-10 07:52:24 UTC
+++ eperl_proto.h
@@ -36,6 +36,7 @@
 #define EPERL_PROTO_H 1
 
 /*_BEGIN_PROTO_*/
+#include "eperl_getopt.h"
 
 /* eperl_main.c */
 extern int mode;
@@ -79,9 +80,14 @@ extern char *strnchr(char *buf, char chr, int n);
 extern char *ePerl_Efwrite(char *cpBuf, int nBuf, int cNum, char *cpOut);
 extern char *ePerl_Cfwrite(char *cpBuf, int nBuf, int cNum, char *cpOut);
 extern char *strnchr(char *buf, char chr, int n);
-extern char *strnstr(char *buf, char *str, int n);
+/*extern char *strnstr(char *buf, char *str, int n);*/
 extern char *strncasestr(char *buf, char *str, int n);
+#if defined(__FreeBSD__)
+#include <osreldate.h>
+#if __FreeBSD_version <= 800057 && __FreeBSD_version > 800000 || __FreeBSD_version <= 701100
 extern char *strndup(char *buf, int n);
+#endif
+#endif
 extern char *ePerl_Bristled2Plain(char *cpBuf);
 
 /* eperl_pp.c */
