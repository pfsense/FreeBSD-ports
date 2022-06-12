--- chrome/browser/chrome_browser_main_extra_parts_linux.cc.orig	2022-05-11 07:16:47 UTC
+++ chrome/browser/chrome_browser_main_extra_parts_linux.cc
@@ -120,7 +120,7 @@ ChromeBrowserMainExtraPartsLinux::ChromeBrowserMainExt
 ChromeBrowserMainExtraPartsLinux::~ChromeBrowserMainExtraPartsLinux() = default;
 
 void ChromeBrowserMainExtraPartsLinux::PreEarlyInitialization() {
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   // On the desktop, we fix the platform name if necessary.
   // See https://crbug.com/1246928.
   auto* const command_line = base::CommandLine::ForCurrentProcess();
