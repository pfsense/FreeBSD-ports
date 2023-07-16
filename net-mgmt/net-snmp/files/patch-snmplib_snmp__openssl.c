--- snmplib/snmp_openssl.c.orig	2023-07-10 12:08:36 UTC
+++ snmplib/snmp_openssl.c
@@ -126,7 +126,6 @@ void netsnmp_init_openssl(void) {
 #ifdef HAVE_SSL_LOAD_ERROR_STRINGS
     SSL_load_error_strings();
 #endif
-    ERR_load_BIO_strings();
     OpenSSL_add_all_algorithms();
 }
 
@@ -906,7 +905,7 @@ netsnmp_openssl_err_log(const char *prefix)
     for (err = ERR_get_error(); err; err = ERR_get_error()) {
         snmp_log(LOG_ERR,"%s: %ld\n", prefix ? prefix: "openssl error", err);
         snmp_log(LOG_ERR, "library=%d, function=%d, reason=%d\n",
-                 ERR_GET_LIB(err), ERR_GET_FUNC(err), ERR_GET_REASON(err));
+                 ERR_GET_LIB(err), 0, ERR_GET_REASON(err));
     }
 }
 #endif /* NETSNMP_FEATURE_REMOVE_OPENSSL_ERR_LOG */
