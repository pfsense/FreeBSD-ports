--- snmplib/transports/snmpTLSBaseDomain.c.orig	2023-07-10 12:08:02 UTC
+++ snmplib/transports/snmpTLSBaseDomain.c
@@ -54,17 +54,6 @@ int openssl_local_index;
 
 int openssl_local_index;
 
-#ifndef HAVE_ERR_GET_ERROR_ALL
-/* A backport of the OpenSSL 1.1.1e ERR_get_error_all() function. */
-static unsigned long ERR_get_error_all(const char **file, int *line,
-                                       const char **func,
-                                       const char **data, int *flags)
-{
-    *func = NULL;
-    return ERR_get_error_line_data(file, line, data, flags);
-}
-#endif
-
 /* this is called during negotiation */
 int verify_callback(int ok, X509_STORE_CTX *ctx) {
     int err, depth;
