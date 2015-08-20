--- src/libhydra/plugins/kernel_pfkey/kernel_pfkey_ipsec.c.orig	2014-11-30 21:32:24.000000000 +0100
+++ src/libhydra/plugins/kernel_pfkey/kernel_pfkey_ipsec.c	2014-11-30 21:33:03.000000000 +0100
@@ -123,7 +123,7 @@
 
 /* from linux/udp.h */
 #ifndef UDP_ENCAP
-#define UDP_ENCAP 100
+#define UDP_ENCAP 1
 #endif
 
 #ifndef UDP_ENCAP_ESPINUDP
@@ -822,13 +822,13 @@
 /*	{ENCR_DES_IV32,				0							}, */
 	{ENCR_NULL,					SADB_EALG_NULL				},
 	{ENCR_AES_CBC,				SADB_X_EALG_AESCBC			},
-/*	{ENCR_AES_CTR,				SADB_X_EALG_AESCTR			}, */
+	{ENCR_AES_CTR,				SADB_X_EALG_AESCTR			},
 /*  {ENCR_AES_CCM_ICV8,			SADB_X_EALG_AES_CCM_ICV8	}, */
 /*	{ENCR_AES_CCM_ICV12,		SADB_X_EALG_AES_CCM_ICV12	}, */
 /*	{ENCR_AES_CCM_ICV16,		SADB_X_EALG_AES_CCM_ICV16	}, */
-/*	{ENCR_AES_GCM_ICV8,			SADB_X_EALG_AES_GCM_ICV8	}, */
-/*	{ENCR_AES_GCM_ICV12,		SADB_X_EALG_AES_GCM_ICV12	}, */
-/*	{ENCR_AES_GCM_ICV16,		SADB_X_EALG_AES_GCM_ICV16	}, */
+	{ENCR_AES_GCM_ICV8,		SADB_X_EALG_AESGCM8	},
+	{ENCR_AES_GCM_ICV12,		SADB_X_EALG_AESGCM12	},
+	{ENCR_AES_GCM_ICV16,		SADB_X_EALG_AESGCM16	},
 	{END_OF_LIST,				0							},
 };
 
