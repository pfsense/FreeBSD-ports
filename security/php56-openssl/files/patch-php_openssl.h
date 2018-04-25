--- php_openssl.h.orig	2015-09-03 00:02:45 UTC
+++ php_openssl.h
@@ -95,6 +95,11 @@ PHP_FUNCTION(openssl_csr_sign);
 PHP_FUNCTION(openssl_csr_get_subject);
 PHP_FUNCTION(openssl_csr_get_public_key);
 
+PHP_FUNCTION(openssl_crl_new);
+PHP_FUNCTION(openssl_crl_revoke_cert);
+PHP_FUNCTION(openssl_crl_revoke_cert_by_serial);
+PHP_FUNCTION(openssl_crl_export);
+
 PHP_FUNCTION(openssl_spki_new);
 PHP_FUNCTION(openssl_spki_verify);
 PHP_FUNCTION(openssl_spki_export);
