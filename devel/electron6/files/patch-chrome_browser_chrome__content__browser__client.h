--- chrome/browser/chrome_content_browser_client.h.orig	2019-09-10 11:13:38 UTC
+++ chrome/browser/chrome_content_browser_client.h
@@ -380,12 +380,12 @@ class ChromeContentBrowserClient : public content::Con
   void OverridePageVisibilityState(
       content::RenderFrameHost* render_frame_host,
       content::PageVisibilityState* visibility_state) override;
-#if defined(OS_POSIX) && !defined(OS_MACOSX)
+#if defined(OS_POSIX) && !defined(OS_MACOSX) && !defined(OS_BSD)
   void GetAdditionalMappedFilesForChildProcess(
       const base::CommandLine& command_line,
       int child_process_id,
       content::PosixFileDescriptorInfo* mappings) override;
-#endif  // defined(OS_POSIX) && !defined(OS_MACOSX)
+#endif  // defined(OS_POSIX) && !defined(OS_MACOSX) && !defined(OS_BSD)
 #if defined(OS_WIN)
   bool PreSpawnRenderer(sandbox::TargetPolicy* policy) override;
   base::string16 GetAppContainerSidForSandboxType(
