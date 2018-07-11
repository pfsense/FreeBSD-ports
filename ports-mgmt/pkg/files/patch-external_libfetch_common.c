--- external/libfetch/common.c.orig	2017-08-17 03:56:56 UTC
+++ external/libfetch/common.c
@@ -60,6 +60,10 @@
 #define INFTIM (-1)
 #endif
 
+#ifdef WITH_STATIC_ENGINE
+void ENGINE_load_ateccx08(void);
+#endif
+
 /*** Local data **************************************************************/
 
 /*
@@ -842,7 +846,11 @@ fetch_ssl(conn_t *conn, const struct url
 		return (-1);
 	}
 
+#ifdef WITH_STATIC_ENGINE
+	ENGINE_load_ateccx08();
+#endif
 	SSL_load_error_strings();
+	OPENSSL_config(NULL);
 
 	conn->ssl_meth = SSLv23_client_method();
 	conn->ssl_ctx = SSL_CTX_new(conn->ssl_meth);
