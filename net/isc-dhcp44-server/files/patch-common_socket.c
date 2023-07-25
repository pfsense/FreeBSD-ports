--- common/socket.c.orig	2022-09-28 14:39:15 UTC
+++ common/socket.c
@@ -254,6 +254,7 @@ if_register_socket(struct interface_info *info, int fa
 	}
 #endif
 
+#if defined(USE_SOCKET_RECEIVE)
 	/* Bind the socket to this interface's IP address. */
 	if (bind(sock, (struct sockaddr *)&name, name_len) < 0) {
 		log_error("Can't bind to dhcp address: %m");
@@ -263,6 +264,7 @@ if_register_socket(struct interface_info *info, int fa
 		log_error("are not running HP JetAdmin software, which");
 		log_fatal("includes a bootp server.");
 	}
+#endif
 
 #if defined(SO_BINDTODEVICE)
 	/* Bind this socket to this interface. */
