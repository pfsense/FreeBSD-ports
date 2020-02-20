--- device-bsd44.c.orig	2018-02-18 22:45:02 UTC
+++ device-bsd44.c
@@ -126,8 +126,46 @@ ret:
 	return -1;
 }
 
-int setup_allrouters_membership(int sock, struct Interface *iface) { return 0; }
+#define MAX_IFACE 50
+int setup_allrouters_membership(int sock, struct Interface *iface) 
+{
+	static int socket_count = 0;
+	static int msockets[MAX_IFACE] = {};
+	int i;
+	struct ipv6_mreq mreq;
+
+	for (i=0;i<socket_count;i++) {
+		if (msockets[i] == sock) {
+			return 0;
+		}
+	}
+	if (socket_count < MAX_IFACE-1) {
+		msockets[socket_count] = sock;
+		socket_count++;
+		flog(LOG_INFO, "adding ipv6-allrouters on %s, sock: %d, iface->props.if_index:%d",
+			iface->props.name, sock, iface->props.if_index);
+	}
+
+	memset(&mreq, 0, sizeof(mreq));
+	mreq.ipv6mr_interface = iface->props.if_index;
+
+	/* all-routers multicast address */
+	if (inet_pton(AF_INET6, "ff02::2",
+			&mreq.ipv6mr_multiaddr.s6_addr) != 1) {
+		flog(LOG_ERR, "inet_pton failed");
+		return (-1);
+	}
+
+	if (setsockopt(sock, IPPROTO_IPV6, IPV6_JOIN_GROUP,
+			&mreq, sizeof(mreq)) < 0) {
+		flog(LOG_ERR, "can't join ipv6-allrouters on %s, sock: %d, iface->props.if_index:%d, error: %s(%d)",
+			iface->props.name, sock, iface->props.if_index, strerror(errno), errno);
+		return (-1);
+	}
+
+	return 0; 
+}

 int set_interface_linkmtu(const char *iface, uint32_t mtu)
 {
 	dlog(LOG_DEBUG, 4, "setting LinkMTU (%u) for %s is not supported", mtu, iface);
@@ -161,5 +199,5 @@ int check_ip6_forwarding(void)
 int check_ip6_iface_forwarding(const char *iface)
 {
 	dlog(LOG_DEBUG, 4, "checking ipv6 forwarding of interface not supported");
-	return -1;
+	return 1;
 }
