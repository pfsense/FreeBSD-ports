--- agent/mibgroup/ip-mib/data_access/scalars_sysctl.c.orig	2021-05-25 22:19:35 UTC
+++ agent/mibgroup/ip-mib/data_access/scalars_sysctl.c
@@ -267,13 +267,20 @@ netsnmp_arch_ip_scalars_ipv6IpForwarding_set(u_long va
     return 0;
 }
 
-static long ipReasmTimeout_val;
-
-void
-netsnmp_arch_ip_scalars_register_handlers(void)
+long netsnmp_arch_ip_scalars_ipReasmTimeout_get(void)
 {
-    static oid ipReasmTimeout_oid[] = { 1, 3, 6, 1, 2, 1, 4, 13, 0 };
+#ifdef freebsd14
+#define	FRAGTTL_CTL	"net.inet.ip.fragttl"
+    int intval;
 
+    if (sysctlbyname(FRAGTTL_CTL, &intval, &(size_t){sizeof(int)}, NULL, 0) < 0) {
+        DEBUGMSGTL(("access::ipReasmTimeout", "sysctl %s failed - %s\n",
+                    FRAGTTL_CTL,
+                    strerror(errno)));
+		return -1;
+	}
+	return intval;
+#else
     /* 
      * This value is static at compile time on FreeBSD; it really should be a
      * probed via either sysctl or sysconf at runtime as the compiled value and
@@ -282,10 +289,11 @@ netsnmp_arch_ip_scalars_register_handlers(void)
      * Please refer to sys/protosw.h for more details on what this value is (in
      * particular PR_SLOWHZ).
      */
-    ipReasmTimeout_val = IPFRAGTTL / PR_SLOWHZ;
+    return IPFRAGTTL / PR_SLOWHZ;
+#endif
+}
 
-    netsnmp_register_long_instance("ipReasmTimeout",
-                                   ipReasmTimeout_oid,
-                                   OID_LENGTH(ipReasmTimeout_oid),
-                                   &ipReasmTimeout_val, NULL);
+void
+netsnmp_arch_ip_scalars_register_handlers(void)
+{
 }
