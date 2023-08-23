--- chrome/browser/defaults.cc.orig	2023-07-24 14:27:53 UTC
+++ chrome/browser/defaults.cc
@@ -46,7 +46,7 @@ const bool kShowHelpMenuItemIcon = false;
 
 const bool kDownloadPageHasShowInFolder = true;
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 const bool kScrollEventChangesTab = true;
 #else
 const bool kScrollEventChangesTab = false;
