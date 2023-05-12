--- extensions/browser/pref_names.h.orig	2023-03-30 00:33:52 UTC
+++ extensions/browser/pref_names.h
@@ -114,7 +114,7 @@ extern const char kPinnedExtensions[];
 extern const char kStorageGarbageCollect[];
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_FUCHSIA)
+    BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
 // A preference for whether Chrome Apps should be allowed. The default depends
 // on the ChromeAppsDeprecation feature flag, and this pref can extend support
 // for Chrome Apps by enterprise policy.
