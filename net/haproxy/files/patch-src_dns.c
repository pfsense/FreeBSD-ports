--- src/dns.c.orig	2017-04-03 08:28:32 UTC
+++ src/dns.c
@@ -967,7 +967,7 @@ int dns_init_resolvers(int close_socket)
 
 			if (close_socket == 1) {
 				if (curnameserver->dgram) {
-					close(curnameserver->dgram->t.sock.fd);
+					fd_delete(curnameserver->dgram->t.sock.fd);
 					memset(curnameserver->dgram, '\0', sizeof(*dgram));
 					dgram = curnameserver->dgram;
 				}
