--- src/drivers/driver_bsd.c.orig	2019-08-07 06:25:25.000000000 -0700
+++ src/drivers/driver_bsd.c	2021-06-13 23:10:12.570253000 -0700
@@ -649,7 +649,7 @@
 		len = 2048;
 	}
 
-	return len;
+	return (len == 0) ? 2048 : len;
 }
 
 #ifdef HOSTAPD
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
@@ -1080,7 +1084,14 @@
 		mode = 0 /* STA */;
 		break;
 	case IEEE80211_MODE_IBSS:
+		/*
+		 * Ref bin/203086 - FreeBSD's net80211 currently uses
+		 * IFM_IEEE80211_ADHOC.
+		 */
+#if 0
 		mode = IFM_IEEE80211_IBSS;
+#endif
+		mode = IFM_IEEE80211_ADHOC;
 		break;
 	case IEEE80211_MODE_AP:
 		mode = IFM_IEEE80211_HOSTAP;
@@ -1336,14 +1347,18 @@
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
