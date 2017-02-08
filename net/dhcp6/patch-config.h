--- config.h.orig	2016-12-19 08:16:42 UTC
+++ config.h
@@ -162,7 +162,7 @@ struct dhcp6_serverinfo {
 
 /* client status code */
 enum {DHCP6S_INIT, DHCP6S_SOLICIT, DHCP6S_INFOREQ, DHCP6S_REQUEST,
-      DHCP6S_RENEW, DHCP6S_REBIND, DHCP6S_RELEASE, DHCP6S_IDLE};
+      DHCP6S_RENEW, DHCP6S_REBIND, DHCP6S_RELEASE, DHCP6S_IDLE, DHCP6S_EXIT};
 
 struct prefix_ifconf {
 	TAILQ_ENTRY(prefix_ifconf) link;
