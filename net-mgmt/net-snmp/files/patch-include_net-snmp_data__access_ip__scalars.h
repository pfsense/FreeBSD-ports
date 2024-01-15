--- include/net-snmp/data_access/ip_scalars.h.orig	2021-05-25 22:19:35 UTC
+++ include/net-snmp/data_access/ip_scalars.h
@@ -17,6 +17,8 @@ int netsnmp_arch_ip_scalars_ipv6IpDefaultHopLimit_set(
 int netsnmp_arch_ip_scalars_ipv6IpDefaultHopLimit_get(u_long *value);
 int netsnmp_arch_ip_scalars_ipv6IpDefaultHopLimit_set(u_long value);
 
+long netsnmp_arch_ip_scalars_ipReasmTimeout_get(void);
+
 #ifdef __cplusplus
 }
 #endif
