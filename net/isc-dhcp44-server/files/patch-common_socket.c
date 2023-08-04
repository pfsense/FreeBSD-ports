--- common/socket.c.orig	2023-08-04 16:44:50 UTC
+++ common/socket.c
@@ -254,8 +254,17 @@ if_register_socket(struct interface_info *info, int fa
 	}
 #endif
 
+/*
+ * We only need to bind the socket for the DHCPv6 case.
+ * DHCPv4 receives exlusively using the packet filter and only
+ * uses this UDP socket for sending routed (with ARP) unicast.
+ * This is the so-called, "fallback" interface. There is no
+ * need in binding INADDR_ANY for DHCPv4.
+ */
+#if defined(DHCPv6)
 	/* Bind the socket to this interface's IP address. */
-	if (bind(sock, (struct sockaddr *)&name, name_len) < 0) {
+	if ((local_family == AF_INET6) &&
+	    bind(sock, (struct sockaddr *)&name, name_len) < 0) {
 		log_error("Can't bind to dhcp address: %m");
 		log_error("Please make sure there is no other dhcp server");
 		log_error("running and that there's no entry for dhcp or");
@@ -263,6 +272,7 @@ if_register_socket(struct interface_info *info, int fa
 		log_error("are not running HP JetAdmin software, which");
 		log_fatal("includes a bootp server.");
 	}
+#endif
 
 #if defined(SO_BINDTODEVICE)
 	/* Bind this socket to this interface. */
