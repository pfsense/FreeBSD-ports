--- external/libfetch/common.c.orig	2019-09-18 07:11:10 UTC
+++ external/libfetch/common.c
@@ -60,6 +60,11 @@
 #define INFTIM (-1)
 #endif
 
+#ifdef WITH_STATIC_ENGINE
+void ENGINE_load_ateccx08(void);
+static int fetch_ssl_initilized = 0;
+#endif
+
 /*** Local data **************************************************************/
 
 /*
@@ -593,7 +598,7 @@ fetch_ssl_verify_altname(STACK_OF(GENERAL_NAME) *altna
 #else
 		name = sk_GENERAL_NAME_value(altnames, i);
 #endif
-		ns = (const char *)ASN1_STRING_data(name->d.ia5);
+		ns = (const char *)ASN1_STRING_get0_data(name->d.ia5);
 		nslen = (size_t)ASN1_STRING_length(name->d.ia5);
 
 		if (name->type == GEN_DNS && ip == NULL &&
@@ -834,15 +839,33 @@ fetch_ssl(conn_t *conn, const struct url *URL, int ver
 #ifdef WITH_SSL
 	int ret, ssl_err;
 	X509_NAME *name;
+	OPENSSL_INIT_SETTINGS *settings;
 	char *str;
 
+#ifdef WITH_STATIC_ENGINE
+	if (fetch_ssl_initilized == 0) {
+#endif
+
 	/* Init the SSL library and context */
 	if (!SSL_library_init()){
 		fprintf(stderr, "SSL library init failed\n");
 		return (-1);
 	}
 
+#ifdef WITH_STATIC_ENGINE
+	ENGINE_load_builtin_engines();
+	ENGINE_load_ateccx08();
+	OPENSSL_load_builtin_modules();
+	ENGINE_register_all_complete();
+#endif
 	SSL_load_error_strings();
+	settings = OPENSSL_INIT_new();
+	OPENSSL_init_crypto(OPENSSL_INIT_LOAD_CONFIG, settings);
+
+#ifdef WITH_STATIC_ENGINE
+		fetch_ssl_initilized = 1;
+	}
+#endif
 
 	conn->ssl_meth = SSLv23_client_method();
 	conn->ssl_ctx = SSL_CTX_new(conn->ssl_meth);
