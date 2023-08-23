--- extensions/browser/api/api_browser_context_keyed_service_factories.cc.orig	2023-07-21 09:49:17 UTC
+++ extensions/browser/api/api_browser_context_keyed_service_factories.cc
@@ -104,7 +104,7 @@ void EnsureApiBrowserContextKeyedServiceFactoriesBuilt
   MessageService::GetFactoryInstance();
   MessagingAPIMessageFilter::EnsureAssociatedFactoryBuilt();
 #if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_WIN) || \
-    BUILDFLAG(IS_MAC)
+    BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD)
   NetworkingPrivateEventRouterFactory::GetInstance();
 #endif
   OffscreenDocumentManager::GetFactory();
