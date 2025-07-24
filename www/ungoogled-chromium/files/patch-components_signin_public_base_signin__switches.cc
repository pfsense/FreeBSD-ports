--- components/signin/public/base/signin_switches.cc.orig	2025-05-31 17:16:41 UTC
+++ components/signin/public/base/signin_switches.cc
@@ -61,7 +61,7 @@ BASE_FEATURE(kUseHostedDomainForManagementCheckOnSigni
              base::FEATURE_DISABLED_BY_DEFAULT);
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // Enables the History Sync Opt-in on Desktop.
 BASE_FEATURE(kEnableHistorySyncOptin,
              "EnableHistorySyncOptin",
