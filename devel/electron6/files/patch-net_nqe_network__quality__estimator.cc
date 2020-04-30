--- net/nqe/network_quality_estimator.cc.orig	2019-09-10 11:14:10 UTC
+++ net/nqe/network_quality_estimator.cc
@@ -100,7 +100,7 @@ nqe::internal::NetworkID DoGetCurrentNetworkID() {
       case NetworkChangeNotifier::ConnectionType::CONNECTION_ETHERNET:
         break;
       case NetworkChangeNotifier::ConnectionType::CONNECTION_WIFI:
-#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_WIN)
+#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_WIN) || defined(OS_BSD)
         network_id.id = GetWifiSSID();
 #endif
         break;
