--- chrome/browser/download/chrome_download_manager_delegate.cc.orig	2025-01-27 17:37:37 UTC
+++ chrome/browser/download/chrome_download_manager_delegate.cc
@@ -1844,7 +1844,7 @@ bool ChromeDownloadManagerDelegate::IsOpenInBrowserPre
 bool ChromeDownloadManagerDelegate::IsOpenInBrowserPreferredForFile(
     const base::FilePath& path) {
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || \
-    BUILDFLAG(IS_MAC)
+    BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD)
   if (path.MatchesExtension(FILE_PATH_LITERAL(".pdf"))) {
     return !download_prefs_->ShouldOpenPdfInSystemReader();
   }
@@ -1987,7 +1987,7 @@ void ChromeDownloadManagerDelegate::CheckDownloadAllow
     content::CheckDownloadAllowedCallback check_download_allowed_cb) {
   DCHECK_CURRENTLY_ON(BrowserThread::UI);
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || \
-    BUILDFLAG(IS_MAC)
+    BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD)
   // Don't download pdf if it is a file URL, as that might cause an infinite
   // download loop if Chrome is not the system pdf viewer.
   if (url.SchemeIsFile() && download_prefs_->ShouldOpenPdfInSystemReader()) {
@@ -2069,7 +2069,7 @@ void ChromeDownloadManagerDelegate::CheckSavePackageAl
   DCHECK(download_item->IsSavePackageDownload());
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || \
-    BUILDFLAG(IS_MAC)
+    BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD)
   std::optional<enterprise_connectors::AnalysisSettings> settings =
       safe_browsing::DeepScanningRequest::ShouldUploadBinary(download_item);
 
