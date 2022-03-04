--- src/3rdparty/chromium/headless/lib/browser/headless_request_context_manager.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/headless/lib/browser/headless_request_context_manager.cc
@@ -23,7 +23,7 @@ namespace headless {
 
 namespace {
 
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
 static char kProductName[] = "HeadlessChrome";
 #endif
 
@@ -53,7 +53,7 @@ net::NetworkTrafficAnnotationTag GetProxyConfigTraffic
   return traffic_annotation;
 }
 
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
 ::network::mojom::CryptConfigPtr BuildCryptConfigOnce(
     const base::FilePath& user_data_path) {
   static bool done_once = false;
@@ -193,7 +193,7 @@ HeadlessRequestContextManager::HeadlessRequestContextM
     proxy_config_monitor_ = std::make_unique<HeadlessProxyConfigMonitor>(
         base::ThreadTaskRunnerHandle::Get());
   }
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   auto crypt_config = BuildCryptConfigOnce(user_data_path_);
   if (crypt_config)
     content::GetNetworkService()->SetCryptConfig(std::move(crypt_config));
