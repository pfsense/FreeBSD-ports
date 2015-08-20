--- php_openssl.h.orig	2015-07-08 14:55:35 UTC
+++ php_openssl.h
@@ -79,6 +79,11 @@ PHP_FUNCTION(openssl_csr_export_to_file)
 PHP_FUNCTION(openssl_csr_sign);
 PHP_FUNCTION(openssl_csr_get_subject);
 PHP_FUNCTION(openssl_csr_get_public_key);
+
+PHP_FUNCTION(openssl_crl_new);
+PHP_FUNCTION(openssl_crl_revoke_cert);
+PHP_FUNCTION(openssl_crl_revoke_cert_by_serial);
+PHP_FUNCTION(openssl_crl_export);
 #else
 
 #define phpext_openssl_ptr NULL
