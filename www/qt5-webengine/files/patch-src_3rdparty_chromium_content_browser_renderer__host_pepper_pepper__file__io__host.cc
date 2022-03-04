--- src/3rdparty/chromium/content/browser/renderer_host/pepper/pepper_file_io_host.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/content/browser/renderer_host/pepper/pepper_file_io_host.cc
@@ -432,7 +432,7 @@ void PepperFileIOHost::OnLocalFileOpened(
     ppapi::host::ReplyMessageContext reply_context,
     const base::FilePath& path,
     base::File::Error error_code) {
-#if defined(OS_WIN) || defined(OS_LINUX)
+#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_BSD)
   // Quarantining a file before its contents are available is only supported on
   // Windows and Linux.
   if (!FileOpenForWrite(open_flags_) || error_code != base::File::FILE_OK) {
@@ -452,7 +452,7 @@ void PepperFileIOHost::OnLocalFileOpened(
 #endif
 }
 
-#if defined(OS_WIN) || defined(OS_LINUX)
+#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_BSD)
 void PepperFileIOHost::OnLocalFileQuarantined(
     ppapi::host::ReplyMessageContext reply_context,
     const base::FilePath& path,
