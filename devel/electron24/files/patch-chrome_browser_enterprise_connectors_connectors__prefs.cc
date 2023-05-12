--- chrome/browser/enterprise/connectors/connectors_prefs.cc.orig	2023-03-30 00:33:42 UTC
+++ chrome/browser/enterprise/connectors/connectors_prefs.cc
@@ -69,7 +69,7 @@ void RegisterProfilePrefs(PrefRegistrySimple* registry
 }
 
 void RegisterLocalStatePrefs(PrefRegistrySimple* registry) {
-#if !BUILDFLAG(IS_FUCHSIA)
+#if !BUILDFLAG(IS_FUCHSIA) && !BUILDFLAG(IS_BSD)
   registry->RegisterInt64Pref(kLatestCrashReportCreationTime, 0);
 #endif
 }
