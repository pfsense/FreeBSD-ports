--- chrome/browser/profiles/chrome_browser_main_extra_parts_profiles.cc.orig	2019-09-10 11:13:41 UTC
+++ chrome/browser/profiles/chrome_browser_main_extra_parts_profiles.cc
@@ -330,7 +330,7 @@ void ChromeBrowserMainExtraPartsProfiles::
   MediaGalleriesPreferencesFactory::GetInstance();
 #endif
 #if defined(OS_WIN) || defined(OS_MACOSX) || \
-    (defined(OS_LINUX) && !defined(OS_CHROMEOS))
+    (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   metrics::DesktopProfileSessionDurationsServiceFactory::GetInstance();
 #endif
   ModelTypeStoreServiceFactory::GetInstance();
