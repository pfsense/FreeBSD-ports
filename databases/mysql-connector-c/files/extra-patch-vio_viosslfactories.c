--- vio/viosslfactories.c.orig	2021-06-07 04:55:13 UTC
+++ vio/viosslfactories.c
@@ -86,7 +86,7 @@ static my_bool     ssl_initialized         = FALSE;
   mjxx/bg6bOOjpgZapvB6ABWlWmRmAAWFtwIBBQ==
   -----END DH PARAMETERS-----
  */
-static unsigned char dh2048_p[]=
+static unsigned char dhp_2048[]=
 {
   0x8A, 0x5D, 0xFA, 0xC0, 0x66, 0x76, 0x4E, 0x61, 0xFA, 0xCA, 0xC0, 0x37,
   0x57, 0x5C, 0x6D, 0x3F, 0x83, 0x0A, 0xA1, 0xF5, 0xF1, 0xE6, 0x7F, 0x3C,
@@ -112,20 +112,25 @@ static unsigned char dh2048_p[]=
   0x00, 0x05, 0x85, 0xB7,
 };
 
-static unsigned char dh2048_g[]={
+static unsigned char dhg_2048[]={
   0x05,
 };
 
 static DH *get_dh2048(void)
 {
-  DH *dh;
-  if ((dh=DH_new()))
+  DH *dh = DH_new();
+  BIGNUM *dhp_bn, *dhg_bn;
+
+  if (dh != NULL)
   {
-    dh->p=BN_bin2bn(dh2048_p,sizeof(dh2048_p),NULL);
-    dh->g=BN_bin2bn(dh2048_g,sizeof(dh2048_g),NULL);
-    if (! dh->p || ! dh->g)
+    dhp_bn = BN_bin2bn(dhp_2048, sizeof (dhp_2048), NULL);
+    dhg_bn = BN_bin2bn(dhg_2048, sizeof (dhg_2048), NULL);
+    if (dhp_bn == NULL || dhg_bn == NULL
+        || !DH_set0_pqg(dh, dhp_bn, NULL, dhg_bn)) 
     {
       DH_free(dh);
+      BN_free(dhp_bn);
+      BN_free(dhg_bn);
       dh=0;
     }
   }
