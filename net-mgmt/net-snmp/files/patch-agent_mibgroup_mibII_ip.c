--- agent/mibgroup/mibII/ip.c.orig	2021-05-25 22:19:35 UTC
+++ agent/mibgroup/mibII/ip.c
@@ -20,6 +20,7 @@
 #include <net-snmp/agent/net-snmp-agent-includes.h>
 #include <net-snmp/agent/auto_nlist.h>
 #include <net-snmp/agent/sysORTable.h>
+#include <net-snmp/data_access/ip_scalars.h>
 
 #include "util_funcs/MIB_STATS_CACHE_TIMEOUT.h"
 #include "ip.h"
@@ -455,7 +456,7 @@ ip_handler(netsnmp_mib_handler          *handler,
         netsnmp_set_request_error(reqinfo, request, SNMP_NOSUCHOBJECT);
         continue;
     case IPREASMTIMEOUT:
-        ret_value = IPFRAGTTL;
+        ret_value = netsnmp_arch_ip_scalars_ipReasmTimeout_get();
         type = ASN_INTEGER;
         break;
     case IPREASMREQDS:
