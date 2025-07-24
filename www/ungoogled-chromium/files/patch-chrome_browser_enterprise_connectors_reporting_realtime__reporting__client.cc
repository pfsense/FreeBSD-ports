--- chrome/browser/enterprise/connectors/reporting/realtime_reporting_client.cc.orig	2025-05-31 17:16:41 UTC
+++ chrome/browser/enterprise/connectors/reporting/realtime_reporting_client.cc
@@ -52,7 +52,7 @@
 #include "base/strings/utf_string_conversions.h"
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/enterprise/signals/signals_aggregator_factory.h"
 #include "chrome/browser/enterprise/signin/enterprise_signin_prefs.h"
 #include "components/device_signals/core/browser/signals_aggregator.h"
@@ -157,7 +157,7 @@ void RealtimeReportingClient::ReportPastEvent(const st
                                      /*include_profile_user_name=*/false);
 }
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 
 void AddCrowdstrikeSignalsToEvent(
     base::Value::Dict& event,
@@ -232,7 +232,7 @@ std::string RealtimeReportingClient::GetBrowserClientI
   return client_id;
 }
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 void RealtimeReportingClient::MaybeCollectDeviceSignalsAndReportEvent(
     ::chrome::cros::reporting::proto::Event event,
     policy::CloudPolicyClient* client,
