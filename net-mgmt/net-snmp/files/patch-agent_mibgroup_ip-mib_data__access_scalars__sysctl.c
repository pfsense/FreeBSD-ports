--- agent/mibgroup/ip-mib/data_access/scalars_sysctl.c.orig	2021-05-25 22:19:35 UTC
+++ agent/mibgroup/ip-mib/data_access/scalars_sysctl.c
@@ -274,6 +274,16 @@ netsnmp_arch_ip_scalars_register_handlers(void)
 {
     static oid ipReasmTimeout_oid[] = { 1, 3, 6, 1, 2, 1, 4, 13, 0 };
 
+#ifdef freebsd14
+#define	FRAGTTL_CTL	"net.inet.ip.fragttl"
+    int intval;
+
+    if (sysctlbyname(FRAGTTL_CTL, &intval, &(size_t){sizeof(int)}, NULL, 0) < 0)
+        DEBUGMSGTL(("access::ipReasmTimeout", "sysctl %s failed - %s\n",
+                    FRAGTTL_CTL,
+                    strerror(errno)));
+    ipReasmTimeout_val = intval;
+#else
     /* 
      * This value is static at compile time on FreeBSD; it really should be a
      * probed via either sysctl or sysconf at runtime as the compiled value and
@@ -283,6 +293,7 @@ netsnmp_arch_ip_scalars_register_handlers(void)
      * particular PR_SLOWHZ).
      */
     ipReasmTimeout_val = IPFRAGTTL / PR_SLOWHZ;
+#endif
 
     netsnmp_register_long_instance("ipReasmTimeout",
                                    ipReasmTimeout_oid,
