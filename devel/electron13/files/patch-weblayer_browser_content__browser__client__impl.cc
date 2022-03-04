--- weblayer/browser/content_browser_client_impl.cc.orig	2021-07-15 19:14:09 UTC
+++ weblayer/browser/content_browser_client_impl.cc
@@ -147,7 +147,7 @@
 
 // TODO(crbug.com/1052397): Revisit once build flag switch of lacros-chrome is
 // complete.
-#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_ANDROID)
+#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_ANDROID) || defined(OS_BSD)
 #include "content/public/common/content_descriptors.h"
 #endif
 
@@ -476,7 +476,7 @@ void ContentBrowserClientImpl::OnNetworkServiceCreated
         embedder_support::GetUserAgent());
 // TODO(crbug.com/1052397): Revisit once build flag switch of lacros-chrome is
 // complete.
-#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_BSD)
   network::mojom::CryptConfigPtr config = network::mojom::CryptConfig::New();
   content::GetNetworkService()->SetCryptConfig(std::move(config));
 #endif
@@ -966,7 +966,7 @@ SafeBrowsingService* ContentBrowserClientImpl::GetSafe
 
 // TODO(crbug.com/1052397): Revisit once build flag switch of lacros-chrome is
 // complete.
-#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_ANDROID)
+#if defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_ANDROID) || defined(OS_BSD)
 void ContentBrowserClientImpl::GetAdditionalMappedFilesForChildProcess(
     const base::CommandLine& command_line,
     int child_process_id,
@@ -1000,7 +1000,7 @@ void ContentBrowserClientImpl::GetAdditionalMappedFile
     mappings->Share(kCrashDumpSignal, crash_signal_fd);
 #endif  // defined(OS_ANDROID)
 }
-#endif  // defined(OS_LINUX)|| defined(OS_ANDROID)
+#endif  // defined(OS_LINUX)|| defined(OS_ANDROID) || defined(OS_BSD)
 
 void ContentBrowserClientImpl::AppendExtraCommandLineSwitches(
     base::CommandLine* command_line,
