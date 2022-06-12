--- net/url_request/url_request_context_builder.cc.orig	2022-05-19 14:06:27 UTC
+++ net/url_request/url_request_context_builder.cc
@@ -498,7 +498,7 @@ std::unique_ptr<URLRequestContext> URLRequestContextBu
   }
 
   if (!proxy_resolution_service_) {
-#if !BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_CHROMEOS) && !BUILDFLAG(IS_ANDROID)
+#if !BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_CHROMEOS) && !BUILDFLAG(IS_ANDROID) && !BUILDFLAG(IS_BSD)
     // TODO(willchan): Switch to using this code when
     // ConfiguredProxyResolutionService::CreateSystemProxyConfigService()'s
     // signature doesn't suck.
