--- chrome/browser/ui/tabs/tab_dialog_manager.cc.orig	2025-09-06 10:01:20 UTC
+++ chrome/browser/ui/tabs/tab_dialog_manager.cc
@@ -88,7 +88,7 @@ bool SupportsGlobalScreenCoordinates() {
 }
 
 bool PlatformClipsChildrenToViewport() {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return true;
 #else
   return false;
