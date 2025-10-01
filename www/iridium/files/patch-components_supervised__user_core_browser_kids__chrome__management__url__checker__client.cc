--- components/supervised_user/core/browser/kids_chrome_management_url_checker_client.cc.orig	2025-09-11 13:19:19 UTC
+++ components/supervised_user/core/browser/kids_chrome_management_url_checker_client.cc
@@ -66,7 +66,7 @@ void OnResponse(
 }
 
 FetcherConfig GetFetcherConfig(bool is_subject_to_parental_controls) {
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
   // Supervised users on these platforms might get into a state where their
   // credentials are not available, so best-effort access mode is a graceful
   // fallback here.
