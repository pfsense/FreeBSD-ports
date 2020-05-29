--- src/drivers/driver_bsd.c.orig2	2019-08-07 06:25:25.000000000 -0700
+++ src/drivers/driver_bsd.c	2020-05-19 21:11:18.891164000 -0700
@@ -665,7 +665,11 @@
 static int bsd_sta_deauth(void *priv, const u8 *own_addr, const u8 *addr,
 			  u16 reason_code);
 
+#ifdef __DragonFly__
+const char *
+#else
 static const char *
+#endif
 ether_sprintf(const u8 *addr)
 {
 	static char buf[sizeof(MACSTR)];
@@ -1336,14 +1340,18 @@
 		drv = bsd_get_drvindex(global, ifm->ifm_index);
 		if (drv == NULL)
 			return;
-		if ((ifm->ifm_flags & IFF_UP) == 0 &&
-		    (drv->flags & IFF_UP) != 0) {
+		if (((ifm->ifm_flags & IFF_UP) == 0 ||
+		    (ifm->ifm_flags & IFF_RUNNING) == 0) &&
+		    (drv->flags & IFF_UP) != 0 &&
+		    (drv->flags & IFF_RUNNING) != 0) {
 			wpa_printf(MSG_DEBUG, "RTM_IFINFO: Interface '%s' DOWN",
 				   drv->ifname);
 			wpa_supplicant_event(drv->ctx, EVENT_INTERFACE_DISABLED,
 					     NULL);
 		} else if ((ifm->ifm_flags & IFF_UP) != 0 &&
-		    (drv->flags & IFF_UP) == 0) {
+		    (ifm->ifm_flags & IFF_RUNNING) != 0 &&
+		    ((drv->flags & IFF_UP) == 0 ||
+		    (drv->flags & IFF_RUNNING)  == 0)) {
 			wpa_printf(MSG_DEBUG, "RTM_IFINFO: Interface '%s' UP",
 				   drv->ifname);
 			wpa_supplicant_event(drv->ctx, EVENT_INTERFACE_ENABLED,
