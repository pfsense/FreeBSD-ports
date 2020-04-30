--- chrome/browser/ui/views/frame/browser_frame.cc.orig	2019-12-12 12:39:17 UTC
+++ chrome/browser/ui/views/frame/browser_frame.cc
@@ -46,7 +46,7 @@
 namespace {
 
 bool IsUsingGtkTheme(Profile* profile) {
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   return ThemeServiceFactory::GetForProfile(profile)->UsingSystemTheme();
 #else
   return false;
