--- components/regional_capabilities/regional_capabilities_switches.cc.orig	2025-08-26 20:49:50 UTC
+++ components/regional_capabilities/regional_capabilities_switches.cc
@@ -18,7 +18,7 @@ BASE_FEATURE(kMitigateLegacySearchEnginePromoOverlap,
              base::FEATURE_DISABLED_BY_DEFAULT);
 #endif
 
-#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD) 
 BASE_FEATURE(kUseFinchPermanentCountryForFetchCountryId,
              "UseFinchPermanentCountyForFetchCountryId",
              base::FEATURE_DISABLED_BY_DEFAULT);
