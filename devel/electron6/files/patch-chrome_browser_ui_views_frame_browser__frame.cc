--- chrome/browser/ui/views/frame/browser_frame.cc.orig	2019-09-10 11:13:44 UTC
+++ chrome/browser/ui/views/frame/browser_frame.cc
@@ -39,7 +39,7 @@
 #include "components/user_manager/user_manager.h"
 #endif
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 #include "chrome/browser/ui/views/frame/browser_command_handler_linux.h"
 #endif
 
@@ -50,7 +50,7 @@
 namespace {
 
 bool IsUsingGtkTheme(Profile* profile) {
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   return ThemeServiceFactory::GetForProfile(profile)->UsingSystemTheme();
 #else
   return false;
@@ -111,7 +111,7 @@ void BrowserFrame::InitBrowserFrame() {
     non_client_view()->set_context_menu_controller(this);
   }
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   browser_command_handler_.reset(new BrowserCommandHandlerLinux(browser_view_));
 #endif
 }
