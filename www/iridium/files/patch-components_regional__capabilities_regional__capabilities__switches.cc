--- components/regional_capabilities/regional_capabilities_switches.cc.orig	2025-05-07 06:48:23 UTC
+++ components/regional_capabilities/regional_capabilities_switches.cc
@@ -6,7 +6,7 @@
 
 namespace switches {
 
-#if BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 BASE_FEATURE(kClearPrefForUnknownCountry,
              "ClearCountryPrefForStoredUnknownCountry",
              base::FEATURE_ENABLED_BY_DEFAULT);
