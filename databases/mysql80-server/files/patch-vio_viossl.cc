--- vio/viossl.cc.orig	2019-09-20 08:30:51 UTC
+++ vio/viossl.cc
@@ -45,7 +45,8 @@
   BIO_set_callback_ex was added in openSSL 1.1.1
   For older openSSL, use the deprecated BIO_set_callback.
 */
-#if OPENSSL_VERSION_NUMBER >= 0x10101000L
+#if OPENSSL_VERSION_NUMBER >= 0x10101000L && \
+    !defined(LIBRESSL_VERSION_NUMBER)
 #define HAVE_BIO_SET_CALLBACK_EX
 #endif
 
@@ -635,7 +636,7 @@ static int ssl_do(struct st_VioSSLFd *ptr, Vio *vio, l
 #if !defined(DBUG_OFF)
     {
       STACK_OF(SSL_COMP) *ssl_comp_methods = nullptr;
-      ssl_comp_methods = SSL_COMP_get_compression_methods();
+      ssl_comp_methods = (STACK_OF(SSL_COMP) *)SSL_COMP_get_compression_methods();
       n = sk_SSL_COMP_num(ssl_comp_methods);
       DBUG_PRINT("info", ("Available compression methods:\n"));
       if (n == 0)
@@ -643,7 +644,7 @@ static int ssl_do(struct st_VioSSLFd *ptr, Vio *vio, l
       else
         for (j = 0; j < n; j++) {
           SSL_COMP *c = sk_SSL_COMP_value(ssl_comp_methods, j);
-#if OPENSSL_VERSION_NUMBER < 0x10100000L
+#if OPENSSL_VERSION_NUMBER < 0x10100000L || defined(LIBRESSL_VERSION_NUMBER)
           DBUG_PRINT("info", ("  %d: %s\n", c->id, c->name));
 #else  /* OPENSSL_VERSION_NUMBER < 0x10100000L */
           DBUG_PRINT("info",
