--- device-common.c.orig	2017-06-29 04:32:29 UTC
+++ device-common.c
@@ -20,10 +20,24 @@
 
 int check_device(int sock, struct Interface *iface)
 {
+	short index;
 	struct ifreq ifr;
+	struct ifmediareq ifmr;
+	struct ifmibdata ifmd;
+	int mib[6];
+	size_t len;
+
 	memset(&ifr, 0, sizeof(ifr));
 	strncpy(ifr.ifr_name, iface->props.name, IFNAMSIZ - 1);
 
+	if (ioctl(sock, SIOCGIFINDEX, &ifr) < 0) {
+		flog(LOG_ERR, "ioctl(SIOCGIFINDEX) failed on %s: %s", iface->props.name, strerror(errno));
+		return -1;
+	} else {
+		dlog(LOG_ERR, 5, "ioctl(SIOCGIFINDEX) succeeded on %s", iface->props.name);
+	}
+	index = ifr.ifr_index;
+
 	if (ioctl(sock, SIOCGIFFLAGS, &ifr) < 0) {
 		flog(LOG_ERR, "ioctl(SIOCGIFFLAGS) failed on %s: %s", iface->props.name, strerror(errno));
 		return -1;
@@ -52,6 +66,43 @@ int check_device(int sock, struct Interf
 		dlog(LOG_ERR, 4, "%s supports multicast", iface->props.name);
 	}
 
+	mib[0] = CTL_NET;
+	mib[1] = PF_LINK;
+	mib[2] = NETLINK_GENERIC;
+	mib[3] = IFMIB_IFDATA;
+	mib[4] = index;
+	mib[5] = IFDATA_GENERAL;
+
+	len = sizeof(ifmd);
+	if (sysctl(mib, 6, &ifmd, &len, 0, 0) < 0) {
+		flog(LOG_ERR, "sysctl ifdata failed on %s: %s", iface->props.name, strerror(errno));
+		return -1;
+	} else {
+		dlog(LOG_ERR, 5, "sysctl ifdata succeeded on %s", iface->props.name);
+	}
+
+	if (ifmd.ifmd_data.ifi_type != IFT_ETHER && ifmd.ifmd_data.ifi_type != IFT_L2VLAN)
+		return 0;
+
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
 
