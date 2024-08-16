--- zebra/if_ioctl.c.orig	2024-08-15 18:38:45 UTC
+++ zebra/if_ioctl.c
@@ -295,6 +295,8 @@ void interface_list(struct zebra_ns *zns)
 	   /proc/net/if_inet6. */
 	ifaddr_proc_ipv6();
 #endif /* HAVE_PROC_NET_IF_INET6 */
+
+	zebra_dplane_startup_stage(zns, ZEBRA_DPLANE_INTERFACES_READ);
 }
 
 #endif /* OPEN_BSD */
