--- extensions/browser/pref_names.cc.orig	2023-03-30 00:33:52 UTC
+++ extensions/browser/pref_names.cc
@@ -53,7 +53,7 @@ const char kManifestV2Availability[] = "extensions.man
 const char kPinnedExtensions[] = "extensions.pinned_extensions";
 const char kStorageGarbageCollect[] = "extensions.storage.garbagecollect";
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_FUCHSIA)
+    BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
 extern const char kChromeAppsEnabled[] = "extensions.chrome_apps_enabled";
 #endif
 const char kChromeAppsWebViewPermissiveBehaviorAllowed[] =
