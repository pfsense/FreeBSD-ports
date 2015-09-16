--- device-bsd44.c.orig	2012-06-05 16:12:29 UTC
+++ device-bsd44.c
@@ -188,6 +188,27 @@ ret:
 
 int setup_allrouters_membership(struct Interface *iface)
 {
+	struct ipv6_mreq mreq;
+
+	memset(&mreq, 0, sizeof(mreq));
+	mreq.ipv6mr_interface = iface->if_index;
+
+	/* all-routers multicast address */
+	if (inet_pton(AF_INET6, "ff02::2",
+			&mreq.ipv6mr_multiaddr.s6_addr) != 1) {
+		flog(LOG_ERR, "inet_pton failed");
+		return (-1);
+	}
+
+	/* XXX: See pfSense ticket #2878 */
+	setsockopt(sock, IPPROTO_IPV6, IPV6_LEAVE_GROUP, &mreq, sizeof(mreq));
+			
+	if (setsockopt(sock, IPPROTO_IPV6, IPV6_JOIN_GROUP,
+			&mreq, sizeof(mreq)) < 0) {
+		flog(LOG_ERR, "can't join ipv6-allrouters on %s", iface->Name);
+		return (-1);
+	}
+
 	return (0);
 }
 
