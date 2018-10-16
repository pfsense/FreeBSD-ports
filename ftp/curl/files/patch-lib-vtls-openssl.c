--- lib/vtls/openssl.c.orig	2018-09-04 20:46:08 UTC
+++ lib/vtls/openssl.c
@@ -69,7 +69,7 @@
 #include <openssl/ocsp.h>
 #endif
 
-#if (OPENSSL_VERSION_NUMBER >= 0x10000000L) && /* 1.0.0 or later */     \
+#if (OPENSSL_VERSION_NUMBER >= 0x0090800fL) && /* 0.9.8 or later */     \
   !defined(OPENSSL_NO_ENGINE)
 #define USE_OPENSSL_ENGINE
 #include <openssl/engine.h>
@@ -129,12 +129,7 @@
 #define X509_get0_notBefore(x) X509_get_notBefore(x)
 #define X509_get0_notAfter(x) X509_get_notAfter(x)
 #define CONST_EXTS /* nope */
-#ifdef LIBRESSL_VERSION_NUMBER
-static unsigned long OpenSSL_version_num(void)
-{
-  return LIBRESSL_VERSION_NUMBER;
-}
-#else
+#ifndef LIBRESSL_VERSION_NUMBER
 #define OpenSSL_version_num() SSLeay()
 #endif
 #endif
@@ -978,7 +973,7 @@ static int Curl_ossl_init(void)
 
   OPENSSL_load_builtin_modules();
 
-#ifdef HAVE_ENGINE_LOAD_BUILTIN_ENGINES
+#ifdef USE_OPENSSL_ENGINE
   ENGINE_load_builtin_engines();
 #endif
 
@@ -3698,7 +3693,11 @@ static size_t Curl_ossl_version(char *bu
   unsigned long ssleay_value;
   sub[2]='\0';
   sub[1]='\0';
+#ifdef LIBRESSL_VERSION_NUMBER
+  ssleay_value = LIBRESSL_VERSION_NUMBER;
+#else
   ssleay_value = OpenSSL_version_num();
+#endif
   if(ssleay_value < 0x906000) {
     ssleay_value = SSLEAY_VERSION_NUMBER;
     sub[0]='\0';
