--- config.h.orig	1990-11-06 19:53:55 UTC
+++ config.h
@@ -5,6 +5,10 @@
  */
 
 /*************************** autoconf section ************************/
+/*	Can we tell a little more about this system? */
+#ifdef _HAVE_PARAM_H
+# include <sys/param.h>
+#endif
 
 /* standard unix V (?) */
 #ifdef	M_SYSV
@@ -175,9 +179,15 @@ extern char *malloc();
 #endif
 
 /******************* Names of files and environment vars **********************/
+#if (defined(BSD) && (BSD >= 199103))
+# define TMPDIR		"/var/tmp"	/* directory where temp files live */
+# define COMPILED_BY	"{Free,Net,Open,4.4,4.3/Reno}BSD (ported by David O'Brien)"
+#endif
 
 #if ANY_UNIX
-# define TMPDIR		"/usr/tmp"	/* directory where temp files live */
+# ifndef TMPDIR
+#  define TMPDIR		"/usr/tmp"	/* directory where temp files live */
+# endif
 # define TMPNAME	"%s/elvt%04x%03x" /* temp file */
 # define CUTNAME	"%s/elvc%04x%03x" /* cut buffer's temp file */
 # define EXRC		".exrc"		/* init file in current directory */
