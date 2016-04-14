--- streamstat.c.orig	2010-05-13 19:16:11.000000000 +0000
+++ streamstat.c	2010-05-13 19:17:17.000000000 +0000
@@ -31,6 +31,8 @@
 #include <netinet/udp.h>
 #include <unistd.h>
 #include <stdlib.h>
+#include <stdio.h>
+#include <string.h>
 #include "lib.h"
 #include "util.h"
 #include "streams.h"
--- streamstat.c.orig	2016-01-15 08:36:19 UTC
+++ streamstat.c
@@ -282,6 +284,7 @@ char * customize_format(int cols, int ou
 {
 	static char fmtstring[256];
 	int n = (cols - outlen - 1) / 2;
+	if (n < 15) n = 15;	/* minimum required chars for IPv4 */
 	snprintf(fmtstring, 256, fmt, n, n, n, n);
 	return(fmtstring);
 }
