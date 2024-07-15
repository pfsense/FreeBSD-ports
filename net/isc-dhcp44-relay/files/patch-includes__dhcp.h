--- includes/dhcp.h.orig        2020-09-17 06:51:06.557515000 +0200
+++ includes/dhcp.h     2020-09-17 06:52:05.628324000 +0200
@@ -73,6 +73,7 @@

 /* Possible values for hardware type (htype) field... */
 #define HTYPE_ETHER    1               /* Ethernet 10Mbps              */
+#define HTYPE_VIRTUAL  2               /* Loopback / tunnel            */
 #define HTYPE_IEEE802  6               /* IEEE 802.2 Token Ring...     */
 #define HTYPE_FDDI     8               /* FDDI...                      */
 #define HTYPE_INFINIBAND  32           /* IP over Infiniband           */

