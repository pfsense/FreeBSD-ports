--- chrome/browser/metrics/chrome_metrics_service_client.cc.orig	2025-04-22 20:15:27 UTC
+++ chrome/browser/metrics/chrome_metrics_service_client.cc
@@ -197,11 +197,11 @@
 #include "chrome/browser/metrics/google_update_metrics_provider_mac.h"
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "components/metrics/motherboard_metrics_provider.h"
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/metrics/chrome_metrics_service_crash_reporter.h"
 #endif
 
@@ -233,7 +233,7 @@ const char kCrashpadHistogramAllocatorName[] = "Crashp
 // Needs to be kept in sync with the writer in
 // third_party/crashpad/crashpad/handler/handler_main.cc.
 const char kCrashpadHistogramAllocatorName[] = "CrashpadMetrics";
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 base::LazyInstance<ChromeMetricsServiceCrashReporter>::Leaky g_crash_reporter =
     LAZY_INSTANCE_INITIALIZER;
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX)
@@ -537,7 +537,7 @@ void ChromeMetricsServiceClient::RegisterPrefs(PrefReg
 #endif  // BUILDFLAG(IS_CHROMEOS)
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || \
-    BUILDFLAG(IS_MAC)
+    BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD)
   metrics::structured::StructuredMetricsService::RegisterPrefs(registry);
 
 #if !BUILDFLAG(IS_CHROMEOS)
@@ -619,7 +619,7 @@ void ChromeMetricsServiceClient::OnEnvironmentUpdate(s
 void ChromeMetricsServiceClient::OnEnvironmentUpdate(std::string* environment) {
   // TODO(https://bugs.chromium.org/p/crashpad/issues/detail?id=135): call this
   // on Mac when the Crashpad API supports it.
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // Register the environment with the crash reporter. Note that there is a
   // window from startup to this point during which crash reports will not have
   // an environment set.
@@ -721,7 +721,7 @@ void ChromeMetricsServiceClient::Initialize() {
         std::make_unique<metrics::dwa::DwaService>(this, local_state);
   }
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   metrics::structured::Recorder::GetInstance()->SetUiTaskRunner(
       base::SequencedTaskRunner::GetCurrentDefault());
 #endif
@@ -781,7 +781,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
   metrics_service_->RegisterMetricsProvider(
       std::make_unique<metrics::CPUMetricsProvider>());
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   metrics_service_->RegisterMetricsProvider(
       std::make_unique<metrics::MotherboardMetricsProvider>());
 #endif
@@ -870,7 +870,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
       std::make_unique<GoogleUpdateMetricsProviderMac>());
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   metrics_service_->RegisterMetricsProvider(
       std::make_unique<DesktopPlatformFeaturesMetricsProvider>());
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
@@ -974,7 +974,7 @@ void ChromeMetricsServiceClient::RegisterMetricsServic
       std::make_unique<PowerMetricsProvider>());
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   metrics_service_->RegisterMetricsProvider(
       metrics::CreateDesktopSessionMetricsProvider());
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX)
@@ -1160,7 +1160,7 @@ bool ChromeMetricsServiceClient::RegisterForProfileEve
   }
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // This creates the DesktopProfileSessionDurationsServices if it didn't exist
   // already.
   metrics::DesktopProfileSessionDurationsServiceFactory::GetForBrowserContext(
@@ -1509,7 +1509,7 @@ void ChromeMetricsServiceClient::CreateStructuredMetri
   recorder =
       base::MakeRefCounted<metrics::structured::AshStructuredMetricsRecorder>(
           cros_system_profile_provider_.get());
-#elif BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC)
+#elif BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_BSD)
 
   // Make sure that Structured Metrics recording delegates have been created
   // before the service is created. This is handled in other places for ChromeOS
