--- src/plugins/tls/openssl/qsslcontext_openssl.cpp.orig	2023-09-21 19:24:26 UTC
+++ src/plugins/tls/openssl/qsslcontext_openssl.cpp
@@ -49,9 +49,9 @@ extern "C" int q_verify_cookie_callback(SSL *ssl, cons
 }
 #endif // dtls
 
-#ifdef TLS1_3_VERSION
+#if defined(TLS1_3_VERSION) && !defined(LIBRESSL_VERSION_NUMBER)
 extern "C" int q_ssl_sess_set_new_cb(SSL *context, SSL_SESSION *session);
-#endif // TLS1_3_VERSION
+#endif // TLS1_3_VERSION && LIBRESSL_VERSION_NUMBE
 
 static inline QString msgErrorSettingBackendConfig(const QString &why)
 {
@@ -370,9 +370,11 @@ QT_WARNING_POP
         return;
     }
 
+#ifndef LIBRESSL_VERSION_NUMBER
     // A nasty hacked OpenSSL using a level that will make our auto-tests fail:
     if (q_SSL_CTX_get_security_level(sslContext->ctx) > 1 && *forceSecurityLevel())
         q_SSL_CTX_set_security_level(sslContext->ctx, 1);
+#endif // LIBRESSL_VERSION_NUMBER
 
     const long anyVersion =
 #if QT_CONFIG(dtls)
@@ -663,14 +665,14 @@ QT_WARNING_POP
         q_SSL_CTX_set_verify(sslContext->ctx, verificationMode, verificationCallback);
     }
 
-#ifdef TLS1_3_VERSION
+#if defined(TLS1_3_VERSION) && !defined(LIBRESSL_VERSION_NUMBER)
     // NewSessionTicket callback:
     if (mode == QSslSocket::SslClientMode && !isDtls) {
         q_SSL_CTX_sess_set_new_cb(sslContext->ctx, q_ssl_sess_set_new_cb);
         q_SSL_CTX_set_session_cache_mode(sslContext->ctx, SSL_SESS_CACHE_CLIENT);
     }
 
-#endif // TLS1_3_VERSION
+#endif // TLS1_3_VERSION && LIBRESSL_VERSION_NUMBER
 
 #if QT_CONFIG(dtls)
     // DTLS cookies:
@@ -758,6 +760,7 @@ void QSslContext::applyBackendConfig(QSslContext *sslC
     }
 #endif // ocsp
 
+#ifndef LIBRESSL_VERSION_NUMBER
     QSharedPointer<SSL_CONF_CTX> cctx(q_SSL_CONF_CTX_new(), &q_SSL_CONF_CTX_free);
     if (cctx) {
         q_SSL_CONF_CTX_set_ssl_ctx(cctx.data(), sslContext->ctx);
@@ -804,7 +807,9 @@ void QSslContext::applyBackendConfig(QSslContext *sslC
             sslContext->errorStr = msgErrorSettingBackendConfig(QSslSocket::tr("SSL_CONF_finish() failed"));
             sslContext->errorCode = QSslError::UnspecifiedError;
         }
-    } else {
+    } else
+#endif // LIBRESSL_VERSION_NUMBER
+    {
         sslContext->errorStr = msgErrorSettingBackendConfig(QSslSocket::tr("SSL_CONF_CTX_new() failed"));
         sslContext->errorCode = QSslError::UnspecifiedError;
     }
