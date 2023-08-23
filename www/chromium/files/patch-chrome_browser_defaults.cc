--- chrome/browser/defaults.cc.orig	2023-07-16 15:47:57 UTC
+++ chrome/browser/defaults.cc
@@ -46,7 +46,7 @@ const bool kShowHelpMenuItemIcon = false;
 
 const bool kDownloadPageHasShowInFolder = true;
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 const bool kScrollEventChangesTab = true;
 #else
 const bool kScrollEventChangesTab = false;
