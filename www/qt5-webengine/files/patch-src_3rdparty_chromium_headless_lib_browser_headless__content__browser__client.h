--- src/3rdparty/chromium/headless/lib/browser/headless_content_browser_client.h.orig	2019-05-23 12:39:34 UTC
+++ src/3rdparty/chromium/headless/lib/browser/headless_content_browser_client.h
@@ -34,7 +34,7 @@ class HeadlessContentBrowserClient : public content::C
       ::storage::OptionalQuotaSettingsCallback callback) override;
   content::GeneratedCodeCacheSettings GetGeneratedCodeCacheSettings(
       content::BrowserContext* context) override;
-#if defined(OS_POSIX) && !defined(OS_MACOSX)
+#if defined(OS_POSIX) && !defined(OS_MACOSX) && !defined(OS_BSD)
   void GetAdditionalMappedFilesForChildProcess(
       const base::CommandLine& command_line,
       int child_process_id,
