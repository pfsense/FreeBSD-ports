--- chrome/browser/metrics/chrome_metrics_service_client.cc.orig	2023-07-24 14:27:53 UTC
+++ chrome/browser/metrics/chrome_metrics_service_client.cc
@@ -183,7 +183,7 @@
 #include "chrome/notification_helper/notification_helper_constants.h"
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "components/metrics/motherboard_metrics_provider.h"
 #endif
 
@@ -209,7 +209,7 @@
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_ANDROID)
+    BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/metrics/family_link_user_metrics_provider.h"
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX) ||
         // BUILDFLAG(IS_CHROMEOS_LACROS))||BUILDFLAG(IS_ANDROID))
@@ -795,7 +795,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
   metrics_service_->RegisterMetricsProvider(
       std::make_unique<metrics::CPUMetricsProvider>());
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   metrics_service_->RegisterMetricsProvider(
       std::make_unique<metrics::MotherboardMetricsProvider>());
 #endif
@@ -877,7 +877,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
 
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || \
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD) || \
     (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
   metrics_service_->RegisterMetricsProvider(
       std::make_unique<DesktopPlatformFeaturesMetricsProvider>());
@@ -886,7 +886,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
 
 #if BUILDFLAG(ENABLE_SUPERVISED_USERS) &&                             \
     (BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-     BUILDFLAG(IS_CHROMEOS_LACROS))
+     BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD))
   if (base::FeatureList::IsEnabled(
           kExtendFamilyLinkUserLogSegmentToAllPlatforms)) {
     metrics_service_->RegisterMetricsProvider(
@@ -1002,7 +1002,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
       std::make_unique<PowerMetricsProvider>());
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   metrics_service_->RegisterMetricsProvider(
       metrics::CreateDesktopSessionMetricsProvider());
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX)
@@ -1201,7 +1201,7 @@ bool ChromeMetricsServiceClient::RegisterForProfileEve
 #endif
 // TODO(crbug.com/1052397): Revisit the macro expression once build flag switch
 // of lacros-chrome is complete.
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || \
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD) || \
     (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
   // This creates the DesktopProfileSessionDurationsServices if it didn't exist
   // already.
