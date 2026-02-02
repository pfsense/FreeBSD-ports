--- src/decode.h.orig	2025-11-05 14:48:19.000000000 +0000
+++ src/decode.h	2025-12-15 20:02:09.774248000 +0000
@@ -1239,6 +1239,10 @@
 
 #ifndef IPPROTO_SHIM6
 #define IPPROTO_SHIM6 140
+#endif
+
+#ifndef DLT_PPP_ETHER
+#define DLT_PPP_ETHER 51
 #endif
 
 /* Packet Flags */
@@ -1420,6 +1424,9 @@
 {
     /* call the decoder */
     switch (datalink) {
+        case DLT_PPP_ETHER:
+            DecodePPPOESession(tv, dtv, p, data, len);
+            break;
         case LINKTYPE_ETHERNET:
             DecodeEthernet(tv, dtv, p, data, len);
             break;
