--- content/public/common/content_features.h.orig	2023-07-24 14:27:53 UTC
+++ content/public/common/content_features.h
@@ -115,7 +115,7 @@ CONTENT_EXPORT extern const base::FeatureParam<int>
     kFledgeLimitNumAuctionsParam;
 CONTENT_EXPORT BASE_DECLARE_FEATURE(kFractionalScrollOffsets);
 CONTENT_EXPORT BASE_DECLARE_FEATURE(kGreaseUACH);
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 CONTENT_EXPORT BASE_DECLARE_FEATURE(kHandleRendererThreadTypeChangesInBrowser);
 #endif
 CONTENT_EXPORT BASE_DECLARE_FEATURE(kIdleDetection);
