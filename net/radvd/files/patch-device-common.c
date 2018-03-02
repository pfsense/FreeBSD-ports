--- device-common.c.orig	2018-01-25 20:24:00 UTC
+++ device-common.c
@@ -21,6 +21,7 @@
 int check_device(int sock, struct Interface *iface)
 {
 	struct ifreq ifr;
+	struct ifmediareq ifmr;
 	memset(&ifr, 0, sizeof(ifr));
 	strncpy(ifr.ifr_name, iface->props.name, IFNAMSIZ - 1);
 
@@ -52,6 +53,25 @@ int check_device(int sock, struct Interf
 		dlog(LOG_ERR, 4, "%s supports multicast", iface->props.name);
 	}
 
+	memset(&ifmr, 0, sizeof(ifmr));
+	strlcpy(ifmr.ifm_name, iface->props.name, sizeof(ifmr.ifm_name));
+	if (ioctl(sock, SIOCGIFMEDIA, (caddr_t)&ifmr) < 0) {
+		flog(LOG_ERR, "ioctl(SIOCGIFMEDIA) failed on %s: %s", iface->props.name, strerror(errno));
+		return -1;
+	} else {
+		dlog(LOG_ERR, 5, "ioctl(SIOCGIFMEDIA) succeeded on %s", iface->props.name);
+	}
+
+	if ((ifmr.ifm_status & IFM_AVALID) != 0 &&
+	    IFM_TYPE(ifmr.ifm_active) == IFM_ETHER) {
+		if ((ifmr.ifm_status & IFM_ACTIVE) == 0) {
+			dlog(LOG_ERR, 4, "%s is not active (no carrier)", iface->props.name);
+			return -1;
+		} else {
+			dlog(LOG_ERR, 4, "%s is active", iface->props.name);
+		}
+	}
+
 	return 0;
 }
 
