diff -ruN ./suricata-4.1.9.orig/src/suricata-common.h ./suricata-4.1.9/src/suricata-common.h
--- ./suricata-4.1.9.orig/src/suricata-common.h	2020-10-07 13:14:59.000000000 -0400
+++ ./src/suricata-common.h	2020-11-11 13:49:44.000000000 -0500
@@ -36,6 +36,8 @@
 #define _GNU_SOURCE
 #define __USE_GNU
 
+#include "queue.h"
+
 #if HAVE_CONFIG_H
 #include <config.h>
 #endif
