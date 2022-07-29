--- dhcp6.h.orig	2017-02-28 19:06:15 UTC
+++ dhcp6.h
@@ -2,7 +2,7 @@
 /*
  * Copyright (C) 1998 and 1999 WIDE Project.
  * All rights reserved.
- * 
+ *
  * Redistribution and use in source and binary forms, with or without
  * modification, are permitted provided that the following conditions
  * are met:
@@ -14,7 +14,7 @@
  * 3. Neither the name of the project nor the names of its contributors
  *    may be used to endorse or promote products derived from this software
  *    without specific prior written permission.
- * 
+ *
  * THIS SOFTWARE IS PROVIDED BY THE PROJECT AND CONTRIBUTORS ``AS IS'' AND
  * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
  * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
@@ -28,21 +28,9 @@
  * SUCH DAMAGE.
  */
 
-#ifndef __DHCP6_H_DEFINED
-#define __DHCP6_H_DEFINED
+#ifndef _DHCP6_H_
+#define _DHCP6_H_
 
-#ifdef __sun__
-#define	__P(x)	x
-typedef uint8_t u_int8_t;
-#ifndef	U_INT16_T_DEFINED
-#define	U_INT16_T_DEFINED
-typedef uint16_t u_int16_t;
-#endif
-#ifndef	U_INT32_T_DEFINED
-#define	U_INT32_T_DEFINED
-typedef uint32_t u_int32_t;
-#endif
-typedef uint64_t u_int64_t;
 #ifndef CMSG_SPACE
 #define	CMSG_SPACE(l) \
 	((unsigned int)_CMSG_HDR_ALIGN(sizeof (struct cmsghdr) + (l)))
@@ -51,7 +39,6 @@ typedef uint64_t u_int64_t;
 #define	CMSG_LEN(l) \
 	((unsigned int)_CMSG_DATA_ALIGN(sizeof (struct cmsghdr)) + (l))
 #endif
-#endif
 
 /* Error Values */
 #define DH6ERR_FAILURE		16
@@ -108,6 +95,16 @@ typedef uint64_t u_int64_t;
 #define DHCP6_IRT_DEFAULT 86400	/* 1 day */
 #define DHCP6_IRT_MINIMUM 600
 
+TAILQ_HEAD(rawop_list, rawoption);
+struct rawoption {
+	TAILQ_ENTRY(rawoption) link;
+
+	int opnum;
+	char *data;
+	int datalen;
+};
+
+
 /* DUID: DHCP unique Identifier */
 struct duid {
 	size_t duid_len;	/* length */
@@ -121,21 +118,21 @@ struct dhcp6_vbuf {		/* generic variable length buffer
 
 /* option information */
 struct dhcp6_ia {		/* identity association */
-	u_int32_t iaid;
-	u_int32_t t1;
-	u_int32_t t2;
+	uint32_t iaid;
+	uint32_t t1;
+	uint32_t t2;
 };
 
 struct dhcp6_prefix {		/* IA_PA */
-	u_int32_t pltime;
-	u_int32_t vltime;
+	uint32_t pltime;
+	uint32_t vltime;
 	struct in6_addr addr;
 	int plen;
 };
 
 struct dhcp6_statefuladdr {	/* IA_NA */
-	u_int32_t pltime;
-	u_int32_t vltime;
+	uint32_t pltime;
+	uint32_t vltime;
 	struct in6_addr addr;
 };
 
@@ -154,7 +151,7 @@ struct dhcp6_listval {
 
 	union {
 		int uv_num;
-		u_int16_t uv_num16;
+		uint16_t uv_num16;
 		struct in6_addr uv_addr6;
 		struct dhcp6_prefix uv_prefix6;
 		struct dhcp6_statefuladdr uv_statefuladdr6;
@@ -197,6 +194,7 @@ struct dhcp6_optinfo {
 	struct dhcp6_list nispname_list; /* NIS+ domain list */
 	struct dhcp6_list bcmcs_list; /* BCMC server list */
 	struct dhcp6_list bcmcsname_list; /* BCMC domain list */
+	struct rawop_list rawops; /* Raw option list */
 
 	struct dhcp6_vbuf relay_msg; /* relay message */
 #define relaymsg_len relay_msg.dv_len
@@ -212,10 +210,10 @@ struct dhcp6_optinfo {
 	int authalgorithm;
 	int authrdm;
 	/* the followings are effective only when NOINFO is unset */
-	u_int64_t authrd;
+	uint64_t authrd;
 	union {
 		struct {
-			u_int32_t keyid;
+			uint32_t keyid;
 			struct dhcp6_vbuf realm;
 			int offset; /* offset to the HMAC field */
 		} aiu_delayed;
@@ -237,8 +235,8 @@ struct dhcp6_optinfo {
 /* DHCP6 base packet format */
 struct dhcp6 {
 	union {
-		u_int8_t m;
-		u_int32_t x;
+		uint8_t m;
+		uint32_t x;
 	} dh6_msgtypexid;
 	/* options follow */
 } __attribute__ ((__packed__));
@@ -248,8 +246,8 @@ struct dhcp6 {
 
 /* DHCPv6 relay messages */
 struct dhcp6_relay {
-	u_int8_t dh6relay_msgtype;
-	u_int8_t dh6relay_hcnt;
+	uint8_t dh6relay_msgtype;
+	uint8_t dh6relay_hcnt;
 	struct in6_addr dh6relay_linkaddr; /* XXX: badly aligned */
 	struct in6_addr dh6relay_peeraddr; /* ditto */
 	/* options follow */
@@ -313,24 +311,24 @@ struct dhcp6_relay {
 /* The followings are KAME specific. */
 
 struct dhcp6opt {
-	u_int16_t dh6opt_type;
-	u_int16_t dh6opt_len;
+	uint16_t dh6opt_type;
+	uint16_t dh6opt_len;
 	/* type-dependent data follows */
 } __attribute__ ((__packed__));
 
 /* DUID type 1 */
 struct dhcp6opt_duid_type1 {
-	u_int16_t dh6_duid1_type;
-	u_int16_t dh6_duid1_hwtype;
-	u_int32_t dh6_duid1_time;
+	uint16_t dh6_duid1_type;
+	uint16_t dh6_duid1_hwtype;
+	uint32_t dh6_duid1_time;
 	/* link-layer address follows */
 } __attribute__ ((__packed__));
 
 /* Status Code */
 struct dhcp6opt_stcode {
-	u_int16_t dh6_stcode_type;
-	u_int16_t dh6_stcode_len;
-	u_int16_t dh6_stcode_code;
+	uint16_t dh6_stcode_type;
+	uint16_t dh6_stcode_len;
+	uint16_t dh6_stcode_code;
 } __attribute__ ((__packed__));
 
 /*
@@ -339,41 +337,41 @@ struct dhcp6opt_stcode {
  * (IA_NA)
  */
 struct dhcp6opt_ia {
-	u_int16_t dh6_ia_type;
-	u_int16_t dh6_ia_len;
-	u_int32_t dh6_ia_iaid;
-	u_int32_t dh6_ia_t1;
-	u_int32_t dh6_ia_t2;
+	uint16_t dh6_ia_type;
+	uint16_t dh6_ia_len;
+	uint32_t dh6_ia_iaid;
+	uint32_t dh6_ia_t1;
+	uint32_t dh6_ia_t2;
 	/* sub options follow */
 } __attribute__ ((__packed__));
 
 /* IA Addr */
 struct dhcp6opt_ia_addr {
-	u_int16_t dh6_ia_addr_type;
-	u_int16_t dh6_ia_addr_len;
+	uint16_t dh6_ia_addr_type;
+	uint16_t dh6_ia_addr_len;
 	struct in6_addr dh6_ia_addr_addr;
-	u_int32_t dh6_ia_addr_preferred_time;
-	u_int32_t dh6_ia_addr_valid_time;
+	uint32_t dh6_ia_addr_preferred_time;
+	uint32_t dh6_ia_addr_valid_time;
 } __attribute__ ((__packed__));
 
 /* IA_PD Prefix */
 struct dhcp6opt_ia_pd_prefix {
-	u_int16_t dh6_iapd_prefix_type;
-	u_int16_t dh6_iapd_prefix_len;
-	u_int32_t dh6_iapd_prefix_preferred_time;
-	u_int32_t dh6_iapd_prefix_valid_time;
-	u_int8_t dh6_iapd_prefix_prefix_len;
+	uint16_t dh6_iapd_prefix_type;
+	uint16_t dh6_iapd_prefix_len;
+	uint32_t dh6_iapd_prefix_preferred_time;
+	uint32_t dh6_iapd_prefix_valid_time;
+	uint8_t dh6_iapd_prefix_prefix_len;
 	struct in6_addr dh6_iapd_prefix_prefix_addr;
 } __attribute__ ((__packed__));
 
 /* Authentication */
 struct dhcp6opt_auth {
-	u_int16_t dh6_auth_type;
-	u_int16_t dh6_auth_len;
-	u_int8_t dh6_auth_proto;
-	u_int8_t dh6_auth_alg;
-	u_int8_t dh6_auth_rdm;
-	u_int8_t dh6_auth_rdinfo[8];
+	uint16_t dh6_auth_type;
+	uint16_t dh6_auth_len;
+	uint8_t dh6_auth_proto;
+	uint8_t dh6_auth_alg;
+	uint8_t dh6_auth_rdm;
+	uint8_t dh6_auth_rdinfo[8];
 	/* authentication information follows */
 } __attribute__ ((__packed__));
 
@@ -382,4 +380,4 @@ enum { DHCP6_AUTHPROTO_UNDEF = -1, DHCP6_AUTHPROTO_DEL
 enum { DHCP6_AUTHALG_UNDEF = -1, DHCP6_AUTHALG_HMACMD5 = 1 };
 enum { DHCP6_AUTHRDM_UNDEF = -1, DHCP6_AUTHRDM_MONOCOUNTER = 0 };
 
-#endif /*__DHCP6_H_DEFINED*/
+#endif
