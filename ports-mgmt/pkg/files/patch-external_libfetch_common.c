--- external/libfetch/common.c.orig	2017-08-17 03:56:56 UTC
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
@@ -836,13 +841,26 @@ fetch_ssl(conn_t *conn, const struct url
 	X509_NAME *name;
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
+	ENGINE_load_ateccx08();
+#endif
 	SSL_load_error_strings();
+	OPENSSL_config(NULL);
+
+#ifdef WITH_STATIC_ENGINE
+		fetch_ssl_initilized = 1;
+	}
+#endif
 
 	conn->ssl_meth = SSLv23_client_method();
 	conn->ssl_ctx = SSL_CTX_new(conn->ssl_meth);
