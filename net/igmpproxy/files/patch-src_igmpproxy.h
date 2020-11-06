--- src/igmpproxy.h.orig	2018-02-13 19:17:30 UTC
+++ src/igmpproxy.h
@@ -35,9 +35,6 @@
 *   igmpproxy.h - Header file for common includes.
 */
 
-#include "config.h"
-#include "os.h"
-
 #include <errno.h>
 #include <stdarg.h>
 #include <stdio.h>
@@ -60,6 +57,9 @@
 #include <netinet/in.h>
 #include <arpa/inet.h>
 
+#include "os.h"
+#include "config.h"
+
 /*
  * Limit on length of route data
  */
@@ -68,7 +68,7 @@
 #define MAX_IP_HEADER_LEN	60
 #define IP_HEADER_RAOPT_LEN	24
 
-#define MAX_MC_VIFS    32     // !!! check this const in the specific includes
+#define MAX_MC_VIFS    64     // !!! check this const in the specific includes
 #define MAX_UPS_VIFS    8
 
 // Useful macros..
@@ -113,7 +113,7 @@ void my_log( int Serverity, int Errno, const char *Fmt
 
 /* ifvc.c
  */
-#define MAX_IF         40     // max. number of interfaces recognized
+#define MAX_IF         100     // max. number of interfaces recognized
 
 // Interface states
 #define IF_STATE_DISABLED      0   // Interface should be ignored.
