--- src/sock_addr.c.orig	2023-05-09 06:42:36 UTC
+++ src/sock_addr.c
@@ -19,6 +19,9 @@
 
 #include "log.h"
 
+#ifdef EAI_ADDRFAMILY
+#undef EAI_ADDRFAMILY
+#endif
 
 unsigned short sock_addr_get_port (const sock_addr *saddr)
 {
