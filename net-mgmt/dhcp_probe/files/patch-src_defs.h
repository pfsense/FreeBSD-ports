--- src/defs.h.orig	2021-01-18 19:16:10 UTC
+++ src/defs.h
@@ -154,16 +154,16 @@ extern int inet_aton(const char *, struct in_addr *);
 
 /* Prototypes for these routines are missing from some systems. */
 #if !HAVE_DECL_ETHER_NTOA
-extern char *ether_ntoa (const struct ether_addr *e);
+extern char *ether_ntoa (const struct libnet_ether_addr *e);
 #endif
 #if !HAVE_DECL_ETHER_ATON
-extern struct ether_addr *ether_aton(const char *hostname);
+extern struct libnet_ether_addr *ether_aton(const char *hostname);
 #endif
 #if !HAVE_DECL_ETHER_NTOHOST
-extern int ether_ntohost (char *hostname, const struct ether_addr *e);
+extern int ether_ntohost (char *hostname, const struct libnet_ether_addr *e);
 #endif
 #if !HAVE_DECL_ETHER_HOSTTON
-extern int ether_hostton (const char *hostname, struct ether_addr *e);
+extern int ether_hostton (const char *hostname, struct libnet_ether_addr *e);
 #endif
 
 #ifndef ETHERTYPE_IP
@@ -179,11 +179,16 @@ extern int ether_hostton (const char *hostname, struct
    We'll have to rely on our own definition.
 */
 typedef struct my_ether_vlan_header {
-	struct ether_addr	ether_dhost;
-	struct ether_addr	ether_shost;
+	struct libnet_ether_addr	ether_dhost;
+	struct libnet_ether_addr	ether_shost;
 	uint16_t			ether_tpid; /* == 0x8100 == ETHERTYPE_VLAN */
 	uint16_t			ether_tci;  /* user_pri, cfi, vid */
 	uint16_t			ether_type;
 } my_ether_vlan_header_t;
+
+/* libnet 1.1.3+ has ether_addr_octet in struct libnet_ether_addr{} */
+#ifndef STRUCT_ETHER_ADDR_HAS_ETHER_ADDR_OCTET
+#define STRUCT_ETHER_ADDR_HAS_ETHER_ADDR_OCTET 1
+#endif
 
 #endif /* not DEFS_H */
