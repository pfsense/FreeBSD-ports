--- ui/gl/gl_features.cc.orig	2022-04-01 07:48:30 UTC
+++ ui/gl/gl_features.cc
@@ -76,7 +76,7 @@ bool IsDeviceBlocked(const char* field, const std::str
 const base::Feature kDefaultPassthroughCommandDecoder {
   "DefaultPassthroughCommandDecoder",
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_FUCHSIA) ||              \
-    ((BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)) && \
+    ((BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD)) && \
      !defined(CHROMECAST_BUILD)) ||                            \
     BUILDFLAG(IS_MAC)
       base::FEATURE_ENABLED_BY_DEFAULT
