--- chrome/browser/prefs/pref_service_incognito_allowlist.cc.orig	2023-03-30 00:33:43 UTC
+++ chrome/browser/prefs/pref_service_incognito_allowlist.cc
@@ -167,7 +167,7 @@ const char* const kPersistentPrefNames[] = {
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD)
     // Toggleing custom frames affects all open windows in the profile, hence
     // should be written to the regular profile when changed in incognito mode.
     prefs::kUseCustomChromeFrame,
