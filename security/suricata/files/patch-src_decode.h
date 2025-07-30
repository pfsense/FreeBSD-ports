--- src/decode.h.orig	2025-06-25 15:35:22 UTC
+++ src/decode.h
@@ -949,6 +949,10 @@ void DecodeUnregisterCounters(void);
 #define DLT_EN10MB 1
 #endif
 
+#ifndef DLT_PPP_ETHER
+#define DLT_PPP_ETHER 51
+#endif
+
 #ifndef DLT_C_HDLC
 #define DLT_C_HDLC 104
 #endif
@@ -1128,6 +1132,9 @@ static inline void DecodeLinkLayer(ThreadVars *tv, Dec
 {
     /* call the decoder */
     switch (datalink) {
+        case DLT_PPP_ETHER:
+            DecodePPPOESession(tv, dtv, p, data, len);
+            break;
         case LINKTYPE_ETHERNET:
             DecodeEthernet(tv, dtv, p, data, len);
             break;
