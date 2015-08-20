--- streams.c.orig	2010-05-13 19:15:59.000000000 +0000
+++ streams.c	2010-05-13 19:16:37.000000000 +0000
@@ -29,6 +29,7 @@
 #include <netinet/tcp.h>
 #include <netinet/udp.h>
 #include <unistd.h>
+#include <string.h>
 #include "lib.h"
 #include "streams.h"
 
