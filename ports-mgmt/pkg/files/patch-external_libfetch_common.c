--- external/libfetch/common.c.orig	2018-06-28 23:06:13 UTC
+++ external/libfetch/common.c
@@ -843,6 +843,7 @@ fetch_ssl(conn_t *conn, const struct url
 	}
 
 	SSL_load_error_strings();
+	OPENSSL_config(NULL);
 
 	conn->ssl_meth = SSLv23_client_method();
 	conn->ssl_ctx = SSL_CTX_new(conn->ssl_meth);
