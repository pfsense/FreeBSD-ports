--- components/storage_monitor/removable_device_constants.cc.orig	2021-04-14 18:41:00 UTC
+++ components/storage_monitor/removable_device_constants.cc
@@ -10,7 +10,7 @@ namespace storage_monitor {
 const char kFSUniqueIdPrefix[] = "UUID:";
 const char kVendorModelSerialPrefix[] = "VendorModelSerial:";
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 const char kVendorModelVolumeStoragePrefix[] = "VendorModelVolumeStorage:";
 #endif
 
