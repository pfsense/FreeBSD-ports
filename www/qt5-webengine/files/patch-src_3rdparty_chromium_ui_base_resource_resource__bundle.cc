--- src/3rdparty/chromium/ui/base/resource/resource_bundle.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/base/resource/resource_bundle.cc
@@ -844,7 +844,7 @@ ScaleFactor ResourceBundle::GetMaxScaleFactor() const 
 }
 
 ScaleFactor ResourceBundle::GetMaxScaleFactor() const {
-#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   return max_scale_factor_;
 #else
   return GetSupportedScaleFactors().back();
@@ -897,7 +897,7 @@ void ResourceBundle::InitSharedInstance(Delegate* dele
   // On platforms other than iOS, 100P is always a supported scale factor.
   // For Windows we have a separate case in this function.
   supported_scale_factors.push_back(SCALE_FACTOR_100P);
-#if defined(OS_APPLE) || defined(OS_LINUX) || defined(OS_CHROMEOS) || \
+#if defined(OS_APPLE) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD) || \
     defined(OS_WIN)
   supported_scale_factors.push_back(SCALE_FACTOR_200P);
 #endif
