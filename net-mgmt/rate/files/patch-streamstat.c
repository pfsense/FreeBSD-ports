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
