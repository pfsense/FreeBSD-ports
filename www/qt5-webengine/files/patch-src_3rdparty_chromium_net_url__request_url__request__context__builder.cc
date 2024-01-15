--- src/3rdparty/chromium/net/url_request/url_request_context_builder.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/net/url_request/url_request_context_builder.cc
@@ -497,7 +497,7 @@ std::unique_ptr<URLRequestContext> URLRequestContextBu
   }
 
   if (!proxy_resolution_service_) {
-#if !defined(OS_LINUX) && !defined(OS_CHROMEOS) && !defined(OS_ANDROID)
+#if !defined(OS_LINUX) && !defined(OS_CHROMEOS) && !defined(OS_ANDROID) && !defined(OS_BSD)
     // TODO(willchan): Switch to using this code when
     // ConfiguredProxyResolutionService::CreateSystemProxyConfigService()'s
     // signature doesn't suck.
@@ -506,7 +506,7 @@ std::unique_ptr<URLRequestContext> URLRequestContextBu
           ConfiguredProxyResolutionService::CreateSystemProxyConfigService(
               base::ThreadTaskRunnerHandle::Get().get());
     }
-#endif  // !defined(OS_LINUX) && !defined(OS_CHROMEOS) && !defined(OS_ANDROID)
+#endif  // !defined(OS_LINUX) && !defined(OS_CHROMEOS) && !defined(OS_ANDROID) && !defined(OS_BSD)
     proxy_resolution_service_ = CreateProxyResolutionService(
         std::move(proxy_config_service_), context.get(),
         context->host_resolver(), context->network_delegate(),
