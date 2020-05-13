--- external/libfetch/common.c.orig	2020-02-21 14:18:42 UTC
+++ external/libfetch/common.c
@@ -53,6 +53,7 @@ __FBSDID("$FreeBSD: head/lib/libfetch/common.c 347050 
 
 #ifdef WITH_SSL
 #include <openssl/x509v3.h>
+#include <openssl/engine.h>
 #endif
 
 #include "bsd_compat.h"
@@ -63,6 +64,11 @@ __FBSDID("$FreeBSD: head/lib/libfetch/common.c 347050 
 #define INFTIM (-1)
 #endif
 
+#ifdef WITH_STATIC_ENGINE
+void ENGINE_load_ateccx08(void);
+static int fetch_ssl_initilized = 0;
+#endif
+
 /*** Local data **************************************************************/
 
 /*
@@ -923,15 +929,33 @@ fetch_ssl(conn_t *conn, const struct url *URL, int ver
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
