--- plugins/digestmd5.c.orig	2022-02-18 21:50:42 UTC
+++ plugins/digestmd5.c
@@ -80,6 +80,12 @@
 # endif
 #endif /* WITH_DES */
 
+/* legacy provider with openssl 3.0 */
+#if OPENSSL_VERSION_NUMBER >= 0x30000000L
+#  include <openssl/provider.h>
+#  include <openssl/crypto.h>
+#endif
+
 #ifdef WIN32
 # include <winsock2.h>
 #else /* Unix */
@@ -170,6 +176,12 @@ enum Context_type { SERVER = 0, CLIENT = 1 };
 
 typedef struct cipher_context cipher_context_t;
 
+typedef struct crypto_context {
+    void *libctx;
+    cipher_context_t *enc_ctx;
+    cipher_context_t *dec_ctx;
+} crypto_context_t;
+
 /* cached auth info used for fast reauth */
 typedef struct reauth_entry {
     char *authid;
@@ -254,12 +266,12 @@ typedef struct context {
     decode_context_t decode_context;
 
     /* if privacy mode is used use these functions for encode and decode */
+    char *cipher_name;
     cipher_function_t *cipher_enc;
     cipher_function_t *cipher_dec;
     cipher_init_t *cipher_init;
     cipher_free_t *cipher_free;
-    struct cipher_context *cipher_enc_context;
-    struct cipher_context *cipher_dec_context;
+    crypto_context_t crypto;
 } context_t;
 
 struct digest_cipher {
@@ -888,7 +900,7 @@ static int dec_3des(context_t *text,
 		    char *output,
 		    unsigned *outputlen)
 {
-    des_context_t *c = (des_context_t *) text->cipher_dec_context;
+    des_context_t *c = (des_context_t *) text->crypto.dec_ctx;
     int padding, p;
     
     des_ede2_cbc_encrypt((void *) input,
@@ -925,7 +937,7 @@ static int enc_3des(context_t *text,
 		    char *output,
 		    unsigned *outputlen)
 {
-    des_context_t *c = (des_context_t *) text->cipher_enc_context;
+    des_context_t *c = (des_context_t *) text->crypto.enc_ctx;
     int len;
     int paddinglen;
     
@@ -973,7 +985,7 @@ static int init_3des(context_t *text, 
 	return SASL_FAIL;
     memcpy(c->ivec, ((char *) enckey) + 8, 8);
 
-    text->cipher_enc_context = (cipher_context_t *) c;
+    text->crypto.enc_ctx = (cipher_context_t *) c;
 
     /* setup dec context */
     c++;
@@ -987,7 +999,7 @@ static int init_3des(context_t *text, 
     
     memcpy(c->ivec, ((char *) deckey) + 8, 8);
 
-    text->cipher_dec_context = (cipher_context_t *) c;
+    text->crypto.dec_ctx = (cipher_context_t *) c;
     
     return SASL_OK;
 }
@@ -1006,7 +1018,7 @@ static int dec_des(context_t *text, 
 		   char *output,
 		   unsigned *outputlen)
 {
-    des_context_t *c = (des_context_t *) text->cipher_dec_context;
+    des_context_t *c = (des_context_t *) text->crypto.dec_ctx;
     int p, padding = 0;
     
     des_cbc_encrypt((void *) input,
@@ -1046,7 +1058,7 @@ static int enc_des(context_t *text,
 		   char *output,
 		   unsigned *outputlen)
 {
-    des_context_t *c = (des_context_t *) text->cipher_enc_context;
+    des_context_t *c = (des_context_t *) text->crypto.enc_ctx;
     int len;
     int paddinglen;
   
@@ -1093,7 +1105,7 @@ static int init_des(context_t *text,
 
     memcpy(c->ivec, ((char *) enckey) + 8, 8);
     
-    text->cipher_enc_context = (cipher_context_t *) c;
+    text->crypto.enc_ctx = (cipher_context_t *) c;
 
     /* setup dec context */
     c++;
@@ -1102,60 +1114,139 @@ static int init_des(context_t *text,
 
     memcpy(c->ivec, ((char *) deckey) + 8, 8);
     
-    text->cipher_dec_context = (cipher_context_t *) c;
+    text->crypto.dec_ctx = (cipher_context_t *) c;
 
     return SASL_OK;
 }
 
 static void free_des(context_t *text)
 {
-    /* free des contextss. only cipher_enc_context needs to be free'd,
-       since cipher_dec_context was allocated at the same time. */
-    if (text->cipher_enc_context) text->utils->free(text->cipher_enc_context);
+    /* free des contextss. only enc_ctx needs to be free'd,
+       since dec_cxt was allocated at the same time. */
+    if (text->crypto.enc_ctx) {
+        text->utils->free(text->crypto.enc_ctx);
+    }
 }
 
 #endif /* WITH_DES */
 
 #ifdef WITH_RC4
-#ifdef HAVE_OPENSSL
 #include <openssl/evp.h>
 
+#if OPENSSL_VERSION_NUMBER >= 0x30000000L
+typedef struct ossl3_library_context {
+    OSSL_LIB_CTX *libctx;
+    OSSL_PROVIDER *legacy_provider;
+    OSSL_PROVIDER *default_provider;
+} ossl3_context_t;
+
+static int init_ossl3_ctx(context_t *text)
+{
+    ossl3_context_t *ctx = text->utils->malloc(sizeof(ossl3_context_t));
+    if (!ctx) return SASL_NOMEM;
+
+    ctx->libctx = OSSL_LIB_CTX_new();
+    if (!ctx->libctx) {
+        text->utils->free(ctx);
+        return SASL_FAIL;
+    }
+
+    /* Load both legacy and default provider as both may be needed */
+    /* if they fail keep going and an error will be raised when we try to
+     * fetch the cipher later */
+    ctx->legacy_provider = OSSL_PROVIDER_load(ctx->libctx, "legacy");
+    ctx->default_provider = OSSL_PROVIDER_load(ctx->libctx, "default");
+    text->crypto.libctx = (void *)ctx;
+
+    return SASL_OK;
+}
+
+static void free_ossl3_ctx(context_t *text)
+{
+    ossl3_context_t *ctx;
+
+    if (!text->crypto.libctx) return;
+
+    ctx = (ossl3_context_t *)text->crypto.libctx;
+
+    if (ctx->legacy_provider) OSSL_PROVIDER_unload(ctx->legacy_provider);
+    if (ctx->default_provider) OSSL_PROVIDER_unload(ctx->default_provider);
+    if (ctx->libctx) OSSL_LIB_CTX_free(ctx->libctx);
+
+    text->utils->free(ctx);
+    text->crypto.libctx = NULL;
+}
+#endif
+
 static void free_rc4(context_t *text)
 {
-    if (text->cipher_enc_context) {
-        EVP_CIPHER_CTX_free((EVP_CIPHER_CTX *)text->cipher_enc_context);
-        text->cipher_enc_context = NULL;
+    if (text->crypto.enc_ctx) {
+        EVP_CIPHER_CTX_free((EVP_CIPHER_CTX *)text->crypto.enc_ctx);
+        text->crypto.enc_ctx = NULL;
     }
-    if (text->cipher_dec_context) {
-        EVP_CIPHER_CTX_free((EVP_CIPHER_CTX *)text->cipher_dec_context);
-        text->cipher_dec_context = NULL;
+    if (text->crypto.dec_ctx) {
+        EVP_CIPHER_CTX_free((EVP_CIPHER_CTX *)text->crypto.dec_ctx);
+        text->crypto.dec_ctx = NULL;
     }
+#if OPENSSL_VERSION_NUMBER >= 0x30000000L
+    free_ossl3_ctx(text);
+#endif
 }
 
 static int init_rc4(context_t *text,
 		    unsigned char enckey[16],
 		    unsigned char deckey[16])
 {
+    const EVP_CIPHER *cipher;
     EVP_CIPHER_CTX *ctx;
     int rc;
 
-    ctx = EVP_CIPHER_CTX_new();
-    if (ctx == NULL) return SASL_NOMEM;
+#if OPENSSL_VERSION_NUMBER >= 0x30000000L
+    ossl3_context_t *ossl3_ctx;
 
-    rc = EVP_EncryptInit_ex(ctx, EVP_rc4(), NULL, enckey, NULL);
-    if (rc != 1) return SASL_FAIL;
+    rc = init_ossl3_ctx(text);
+    if (rc != SASL_OK) return rc;
 
-    text->cipher_enc_context = (void *)ctx;
+    ossl3_ctx = (ossl3_context_t *)text->crypto.libctx;
+    cipher = EVP_CIPHER_fetch(ossl3_ctx->libctx, "RC4", "");
+#else
+    cipher = EVP_rc4();
+#endif
 
+
     ctx = EVP_CIPHER_CTX_new();
-    if (ctx == NULL) return SASL_NOMEM;
+    if (ctx == NULL) {
+        rc = SASL_NOMEM;
+        goto done;
+    }
 
-    rc = EVP_DecryptInit_ex(ctx, EVP_rc4(), NULL, deckey, NULL);
-    if (rc != 1) return SASL_FAIL;
+    rc = EVP_EncryptInit_ex(ctx, cipher, NULL, enckey, NULL);
+    if (rc != 1) {
+        rc = SASL_FAIL;
+        goto done;
+    }
+    text->crypto.enc_ctx = (void *)ctx;
 
-    text->cipher_dec_context = (void *)ctx;
+    ctx = EVP_CIPHER_CTX_new();
+    if (ctx == NULL) {
+        rc = SASL_NOMEM;
+        goto done;
+    }
 
-    return SASL_OK;
+    rc = EVP_DecryptInit_ex(ctx, cipher, NULL, deckey, NULL);
+    if (rc != 1) {
+        rc = SASL_FAIL;
+        goto done;
+    }
+    text->crypto.dec_ctx = (void *)ctx;
+
+    rc = SASL_OK;
+
+done:
+    if (rc != SASL_OK) {
+        free_rc4(text);
+    }
+    return rc;
 }
 
 static int dec_rc4(context_t *text,
@@ -1169,14 +1260,14 @@ static int dec_rc4(context_t *text,
     int rc;
 
     /* decrypt the text part & HMAC */
-    rc = EVP_DecryptUpdate((EVP_CIPHER_CTX *)text->cipher_dec_context,
+    rc = EVP_DecryptUpdate((EVP_CIPHER_CTX *)text->crypto.dec_ctx,
                            (unsigned char *)output, &len,
                            (const unsigned char *)input, inputlen);
     if (rc != 1) return SASL_FAIL;
 
     *outputlen = len;
 
-    rc = EVP_DecryptFinal_ex((EVP_CIPHER_CTX *)text->cipher_dec_context,
+    rc = EVP_DecryptFinal_ex((EVP_CIPHER_CTX *)text->crypto.dec_ctx,
                              (unsigned char *)output + len, &len);
     if (rc != 1) return SASL_FAIL;
 
@@ -1198,7 +1289,7 @@ static int enc_rc4(context_t *text,
     int len;
     int rc;
     /* encrypt the text part */
-    rc = EVP_EncryptUpdate((EVP_CIPHER_CTX *)text->cipher_enc_context,
+    rc = EVP_EncryptUpdate((EVP_CIPHER_CTX *)text->crypto.enc_ctx,
                            (unsigned char *)output, &len,
                            (const unsigned char *)input, inputlen);
     if (rc != 1) return SASL_FAIL;
@@ -1206,14 +1297,14 @@ static int enc_rc4(context_t *text,
     *outputlen = len;
 
     /* encrypt the `MAC part */
-    rc = EVP_EncryptUpdate((EVP_CIPHER_CTX *)text->cipher_enc_context,
+    rc = EVP_EncryptUpdate((EVP_CIPHER_CTX *)text->crypto.enc_ctx,
                            (unsigned char *)output + *outputlen, &len,
                            digest, 10);
     if (rc != 1) return SASL_FAIL;
 
     *outputlen += len;
 
-    rc = EVP_EncryptFinal_ex((EVP_CIPHER_CTX *)text->cipher_enc_context,
+    rc = EVP_EncryptFinal_ex((EVP_CIPHER_CTX *)text->crypto.enc_ctx,
                              (unsigned char *)output + *outputlen, &len);
     if (rc != 1) return SASL_FAIL;
 
@@ -1221,194 +1312,11 @@ static int enc_rc4(context_t *text,
 
     return SASL_OK;
 }
-#else
-/* quick generic implementation of RC4 */
-struct rc4_context_s {
-    unsigned char sbox[256];
-    int i, j;
-};
-
-typedef struct rc4_context_s rc4_context_t;
-
-static void rc4_init(rc4_context_t *text,
-		     const unsigned char *key,
-		     unsigned keylen)
-{
-    int i, j;
-    
-    /* fill in linearly s0=0 s1=1... */
-    for (i=0;i<256;i++)
-	text->sbox[i]=i;
-    
-    j=0;
-    for (i = 0; i < 256; i++) {
-	unsigned char tmp;
-	/* j = (j + Si + Ki) mod 256 */
-	j = (j + text->sbox[i] + key[i % keylen]) % 256;
-	
-	/* swap Si and Sj */
-	tmp = text->sbox[i];
-	text->sbox[i] = text->sbox[j];
-	text->sbox[j] = tmp;
-    }
-    
-    /* counters initialized to 0 */
-    text->i = 0;
-    text->j = 0;
-}
-
-static void rc4_encrypt(rc4_context_t *text,
-			const char *input,
-			char *output,
-			unsigned len)
-{
-    int tmp;
-    int i = text->i;
-    int j = text->j;
-    int t;
-    int K;
-    const char *input_end = input + len;
-    
-    while (input < input_end) {
-	i = (i + 1) % 256;
-	
-	j = (j + text->sbox[i]) % 256;
-	
-	/* swap Si and Sj */
-	tmp = text->sbox[i];
-	text->sbox[i] = text->sbox[j];
-	text->sbox[j] = tmp;
-	
-	t = (text->sbox[i] + text->sbox[j]) % 256;
-	
-	K = text->sbox[t];
-	
-	/* byte K is Xor'ed with plaintext */
-	*output++ = *input++ ^ K;
-    }
-    
-    text->i = i;
-    text->j = j;
-}
-
-static void rc4_decrypt(rc4_context_t *text,
-			const char *input,
-			char *output,
-			unsigned len)
-{
-    int tmp;
-    int i = text->i;
-    int j = text->j;
-    int t;
-    int K;
-    const char *input_end = input + len;
-    
-    while (input < input_end) {
-	i = (i + 1) % 256;
-	
-	j = (j + text->sbox[i]) % 256;
-	
-	/* swap Si and Sj */
-	tmp = text->sbox[i];
-	text->sbox[i] = text->sbox[j];
-	text->sbox[j] = tmp;
-	
-	t = (text->sbox[i] + text->sbox[j]) % 256;
-	
-	K = text->sbox[t];
-	
-	/* byte K is Xor'ed with plaintext */
-	*output++ = *input++ ^ K;
-    }
-    
-    text->i = i;
-    text->j = j;
-}
-
-static void free_rc4(context_t *text)
-{
-    /* free rc4 context structures */
-
-    if (text->cipher_enc_context) {
-        text->utils->free(text->cipher_enc_context);
-        text->cipher_enc_context = NULL;
-    }
-    if (text->cipher_dec_context) {
-        text->utils->free(text->cipher_dec_context);
-        text->cipher_dec_context = NULL;
-    }
-}
-
-static int init_rc4(context_t *text, 
-		    unsigned char enckey[16],
-		    unsigned char deckey[16])
-{
-    /* allocate rc4 context structures */
-    text->cipher_enc_context=
-	(cipher_context_t *) text->utils->malloc(sizeof(rc4_context_t));
-    if (text->cipher_enc_context == NULL) return SASL_NOMEM;
-    
-    text->cipher_dec_context=
-	(cipher_context_t *) text->utils->malloc(sizeof(rc4_context_t));
-    if (text->cipher_dec_context == NULL) return SASL_NOMEM;
-    
-    /* initialize them */
-    rc4_init((rc4_context_t *) text->cipher_enc_context,
-             (const unsigned char *) enckey, 16);
-    rc4_init((rc4_context_t *) text->cipher_dec_context,
-             (const unsigned char *) deckey, 16);
-    
-    return SASL_OK;
-}
-
-static int dec_rc4(context_t *text,
-		   const char *input,
-		   unsigned inputlen,
-		   unsigned char digest[16] __attribute__((unused)),
-		   char *output,
-		   unsigned *outputlen)
-{
-    /* decrypt the text part & HMAC */
-    rc4_decrypt((rc4_context_t *) text->cipher_dec_context, 
-                input, output, inputlen);
-
-    /* no padding so we just subtract the HMAC to get the text length */
-    *outputlen = inputlen - 10;
-    
-    return SASL_OK;
-}
-
-static int enc_rc4(context_t *text,
-		   const char *input,
-		   unsigned inputlen,
-		   unsigned char digest[16],
-		   char *output,
-		   unsigned *outputlen)
-{
-    /* pad is zero */
-    *outputlen = inputlen+10;
-    
-    /* encrypt the text part */
-    rc4_encrypt((rc4_context_t *) text->cipher_enc_context,
-                input,
-                output,
-                inputlen);
-    
-    /* encrypt the HMAC part */
-    rc4_encrypt((rc4_context_t *) text->cipher_enc_context, 
-                (const char *) digest, 
-		(output)+inputlen, 10);
-    
-    return SASL_OK;
-}
-#endif /* HAVE_OPENSSL */
 #endif /* WITH_RC4 */
 
 struct digest_cipher available_ciphers[] =
 {
 #ifdef WITH_RC4
-    { "rc4-40", 40, 5, 0x01, &enc_rc4, &dec_rc4, &init_rc4, &free_rc4 },
-    { "rc4-56", 56, 7, 0x02, &enc_rc4, &dec_rc4, &init_rc4, &free_rc4 },
     { "rc4", 128, 16, 0x04, &enc_rc4, &dec_rc4, &init_rc4, &free_rc4 },
 #endif
 #ifdef WITH_DES
@@ -2821,6 +2729,7 @@ static int digestmd5_server_mech_step2(server_context_
 	}
 	
 	if (cptr->name) {
+	    text->cipher_name = cptr->name;
 	    text->cipher_enc = cptr->cipher_enc;
 	    text->cipher_dec = cptr->cipher_dec;
 	    text->cipher_init = cptr->cipher_init;
@@ -2964,7 +2873,10 @@ static int digestmd5_server_mech_step2(server_context_
 	if (text->cipher_init) {
 	    if (text->cipher_init(text, enckey, deckey) != SASL_OK) {
 		sparams->utils->seterror(sparams->utils->conn, 0,
-					 "couldn't init cipher");
+					 "couldn't init cipher '%s'",
+                                         text->cipher_name);
+                result = SASL_FAIL;
+                goto FreeAllMem;
 	    }
 	}
     }
@@ -3515,6 +3427,7 @@ static int make_client_response(context_t *text,
 	oparams->mech_ssf = ctext->cipher->ssf;
 
 	nbits = ctext->cipher->n;
+	text->cipher_name = ctext->cipher->name;
 	text->cipher_enc = ctext->cipher->cipher_enc;
 	text->cipher_dec = ctext->cipher->cipher_dec;
 	text->cipher_free = ctext->cipher->cipher_free;
@@ -3739,7 +3652,13 @@ static int make_client_response(context_t *text,
 	
 	/* initialize cipher if need be */
 	if (text->cipher_init) {
-	    text->cipher_init(text, enckey, deckey);
+	    if (text->cipher_init(text, enckey, deckey) != SASL_OK) {
+	        params->utils->seterror(params->utils->conn, 0,
+		         "internal error: failed to init cipher '%s'",
+                         text->cipher_name);
+                result = SASL_FAIL;
+                goto FreeAllocatedMem;
+            }
 	}
     }
     
