--- mysys_ssl/my_aes_openssl.cc.orig	2021-06-07 05:16:32 UTC
+++ mysys_ssl/my_aes_openssl.cc
@@ -122,7 +122,7 @@ int my_aes_encrypt(const unsigned char *source, uint32
                    enum my_aes_opmode mode, const unsigned char *iv,
                    bool padding)
 {
-  EVP_CIPHER_CTX ctx;
+  EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
   const EVP_CIPHER *cipher= aes_evp_type(mode);
   int u_len, f_len;
   /* The real key to be used for encryption */
@@ -132,23 +132,23 @@ int my_aes_encrypt(const unsigned char *source, uint32
   if (!cipher || (EVP_CIPHER_iv_length(cipher) > 0 && !iv))
     return MY_AES_BAD_DATA;
 
-  if (!EVP_EncryptInit(&ctx, cipher, rkey, iv))
+  if (!EVP_EncryptInit(ctx, cipher, rkey, iv))
     goto aes_error;                             /* Error */
-  if (!EVP_CIPHER_CTX_set_padding(&ctx, padding))
+  if (!EVP_CIPHER_CTX_set_padding(ctx, padding))
     goto aes_error;                             /* Error */
-  if (!EVP_EncryptUpdate(&ctx, dest, &u_len, source, source_length))
+  if (!EVP_EncryptUpdate(ctx, dest, &u_len, source, source_length))
     goto aes_error;                             /* Error */
 
-  if (!EVP_EncryptFinal(&ctx, dest + u_len, &f_len))
+  if (!EVP_EncryptFinal(ctx, dest + u_len, &f_len))
     goto aes_error;                             /* Error */
 
-  EVP_CIPHER_CTX_cleanup(&ctx);
+  EVP_CIPHER_CTX_free(ctx);
   return u_len + f_len;
 
 aes_error:
   /* need to explicitly clean up the error if we want to ignore it */
   ERR_clear_error();
-  EVP_CIPHER_CTX_cleanup(&ctx);
+  EVP_CIPHER_CTX_free(ctx);
   return MY_AES_BAD_DATA;
 }
 
@@ -159,7 +159,7 @@ int my_aes_decrypt(const unsigned char *source, uint32
                    bool padding)
 {
 
-  EVP_CIPHER_CTX ctx;
+  EVP_CIPHER_CTX *ctx = EVP_CIPHER_CTX_new();
   const EVP_CIPHER *cipher= aes_evp_type(mode);
   int u_len, f_len;
 
@@ -170,24 +170,22 @@ int my_aes_decrypt(const unsigned char *source, uint32
   if (!cipher || (EVP_CIPHER_iv_length(cipher) > 0 && !iv))
     return MY_AES_BAD_DATA;
 
-  EVP_CIPHER_CTX_init(&ctx);
-
-  if (!EVP_DecryptInit(&ctx, aes_evp_type(mode), rkey, iv))
+  if (!EVP_DecryptInit(ctx, aes_evp_type(mode), rkey, iv))
     goto aes_error;                             /* Error */
-  if (!EVP_CIPHER_CTX_set_padding(&ctx, padding))
+  if (!EVP_CIPHER_CTX_set_padding(ctx, padding))
     goto aes_error;                             /* Error */
-  if (!EVP_DecryptUpdate(&ctx, dest, &u_len, source, source_length))
+  if (!EVP_DecryptUpdate(ctx, dest, &u_len, source, source_length))
     goto aes_error;                             /* Error */
-  if (!EVP_DecryptFinal_ex(&ctx, dest + u_len, &f_len))
+  if (!EVP_DecryptFinal_ex(ctx, dest + u_len, &f_len))
     goto aes_error;                             /* Error */
 
-  EVP_CIPHER_CTX_cleanup(&ctx);
+  EVP_CIPHER_CTX_free(ctx);
   return u_len + f_len;
 
 aes_error:
   /* need to explicitly clean up the error if we want to ignore it */
   ERR_clear_error();
-  EVP_CIPHER_CTX_cleanup(&ctx);
+  EVP_CIPHER_CTX_free(ctx);
   return MY_AES_BAD_DATA;
 }
 
