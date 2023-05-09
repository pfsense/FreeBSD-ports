--- src/sock_addr.c.orig	2023-05-09 06:29:08 UTC
+++ src/sock_addr.c
@@ -13,6 +13,7 @@
 #include <errno.h>
 #include <string.h>
 #ifndef _WIN32
+#undef __BSD_VISIBLE
 #include <netdb.h>
 #include <arpa/inet.h>
 #endif
