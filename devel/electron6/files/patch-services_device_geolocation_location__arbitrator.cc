--- services/device/geolocation/location_arbitrator.cc.orig	2019-09-10 10:42:43 UTC
+++ services/device/geolocation/location_arbitrator.cc
@@ -156,7 +156,7 @@ LocationArbitrator::NewNetworkLocationProvider(
 
 std::unique_ptr<LocationProvider>
 LocationArbitrator::NewSystemLocationProvider() {
-#if defined(OS_WIN) || defined(OS_MACOSX) || defined(OS_LINUX) || \
+#if defined(OS_WIN) || defined(OS_MACOSX) || defined(OS_LINUX) || defined(OS_BSD) || \
     defined(OS_FUCHSIA)
   return nullptr;
 #else
