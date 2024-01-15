--- electron/shell/browser/browser_process_impl.cc.orig	2023-08-12 11:40:00 UTC
+++ electron/shell/browser/browser_process_impl.cc
@@ -304,7 +304,7 @@ const std::string& BrowserProcessImpl::GetSystemLocale
   return system_locale_;
 }
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 void BrowserProcessImpl::SetLinuxStorageBackend(
     os_crypt::SelectedLinuxBackend selected_backend) {
   switch (selected_backend) {
@@ -338,7 +338,7 @@ void BrowserProcessImpl::SetLinuxStorageBackend(
 const std::string& BrowserProcessImpl::GetLinuxStorageBackend() const {
   return selected_linux_storage_backend_;
 }
-#endif  // BUILDFLAG(IS_LINUX)
+#endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 
 void BrowserProcessImpl::SetApplicationLocale(const std::string& locale) {
   locale_ = locale;
