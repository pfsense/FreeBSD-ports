--- net/tools/quic/quic_http_proxy_backend.cc.orig	2021-07-19 18:45:19 UTC
+++ net/tools/quic/quic_http_proxy_backend.cc
@@ -161,7 +161,7 @@ void QuicHttpProxyBackend::InitializeURLRequestContext
   // Enable HTTP2, but disable QUIC on the backend
   context_builder.SetSpdyAndQuicEnabled(true /* http2 */, false /* quic */);
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   // On Linux, use a fixed ProxyConfigService, since the default one
   // depends on glib.
   context_builder.set_proxy_config_service(
