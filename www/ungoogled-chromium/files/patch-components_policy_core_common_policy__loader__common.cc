--- components/policy/core/common/policy_loader_common.cc.orig	2023-09-17 07:59:53 UTC
+++ components/policy/core/common/policy_loader_common.cc
@@ -46,7 +46,7 @@ const char* kSensitivePolicies[] = {
     key::kDefaultSearchProviderEnabled,
     key::kSafeBrowsingEnabled,
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
     key::kAutoOpenFileTypes,
     key::kSafeBrowsingAllowlistDomains,
     key::kHomepageIsNewTabPage,
@@ -55,7 +55,7 @@ const char* kSensitivePolicies[] = {
     key::kRestoreOnStartup,
     key::kRestoreOnStartupURLs,
 #endif
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
     key::kCommandLineFlagSecurityWarningsEnabled,
 #endif
 #if !BUILDFLAG(IS_IOS)
