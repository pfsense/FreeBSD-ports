--- ./src/libhydra/plugins/kernel_pfkey/kernel_pfkey_ipsec.c.orig	2015-09-17 13:59:01.303975920 -0500
+++ ./src/libhydra/plugins/kernel_pfkey/kernel_pfkey_ipsec.c	2015-09-17 14:01:17.305961648 -0500
@@ -129,7 +129,7 @@
 
 /* from linux/udp.h */
 #ifndef UDP_ENCAP
-#define UDP_ENCAP 100
+#define UDP_ENCAP 1
 #endif
 
 #ifndef UDP_ENCAP_ESPINUDP
@@ -843,14 +843,14 @@
 /*	{ENCR_DES_IV32,				0							}, */
 	{ENCR_NULL,					SADB_EALG_NULL				},
 	{ENCR_AES_CBC,				SADB_X_EALG_AESCBC			},
-/*	{ENCR_AES_CTR,				SADB_X_EALG_AESCTR			}, */
+	{ENCR_AES_CTR,				SADB_X_EALG_AESCTR			},
 /*  {ENCR_AES_CCM_ICV8,			SADB_X_EALG_AES_CCM_ICV8	}, */
 /*	{ENCR_AES_CCM_ICV12,		SADB_X_EALG_AES_CCM_ICV12	}, */
 /*	{ENCR_AES_CCM_ICV16,		SADB_X_EALG_AES_CCM_ICV16	}, */
 #ifdef SADB_X_EALG_AES_GCM_ICV8 /* assume the others are defined too */
-	{ENCR_AES_GCM_ICV8,			SADB_X_EALG_AES_GCM_ICV8	},
-	{ENCR_AES_GCM_ICV12,		SADB_X_EALG_AES_GCM_ICV12	},
-	{ENCR_AES_GCM_ICV16,		SADB_X_EALG_AES_GCM_ICV16	},
+	{ENCR_AES_GCM_ICV8,		SADB_X_EALG_AESGCM8	},
+	{ENCR_AES_GCM_ICV12,		SADB_X_EALG_AESGCM12	},
+	{ENCR_AES_GCM_ICV16,		SADB_X_EALG_AESGCM16	},
 #endif
 	{END_OF_LIST,				0							},
 };
