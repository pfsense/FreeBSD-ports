--- services/device/public/cpp/device_features.cc.orig	2025-08-26 20:49:50 UTC
+++ services/device/public/cpp/device_features.cc
@@ -144,7 +144,7 @@ BASE_FEATURE(kAutomaticUsbDetach,
 BASE_FEATURE(kAutomaticUsbDetach,
              "AutomaticUsbDetach",
              base::FEATURE_ENABLED_BY_DEFAULT);
-#elif BUILDFLAG(IS_LINUX)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 BASE_FEATURE(kAutomaticUsbDetach,
              "AutomaticUsbDetach",
              base::FEATURE_DISABLED_BY_DEFAULT);
