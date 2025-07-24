--- headless/lib/browser/headless_content_browser_client.h.orig	2025-05-07 06:48:23 UTC
+++ headless/lib/browser/headless_content_browser_client.h
@@ -47,7 +47,7 @@ class HeadlessContentBrowserClient : public content::C
   CreateDevToolsManagerDelegate() override;
   content::GeneratedCodeCacheSettings GetGeneratedCodeCacheSettings(
       content::BrowserContext* context) override;
-#if BUILDFLAG(IS_POSIX) && !BUILDFLAG(IS_MAC)
+#if BUILDFLAG(IS_POSIX) && !BUILDFLAG(IS_MAC) && !BUILDFLAG(IS_BSD)
   void GetAdditionalMappedFilesForChildProcess(
       const base::CommandLine& command_line,
       int child_process_id,
