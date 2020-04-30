--- components/sync_device_info/device_info_sync_bridge.cc.orig	2020-03-16 18:40:31 UTC
+++ components/sync_device_info/device_info_sync_bridge.cc
@@ -456,11 +456,13 @@ void DeviceInfoSyncBridge::OnStoreCreated(
     return;
   }
 
+#if !defined(OS_BSD)
   store_ = std::move(store);
 
   base::SysInfo::GetHardwareInfo(
       base::BindOnce(&DeviceInfoSyncBridge::OnHardwareInfoRetrieved,
                      weak_ptr_factory_.GetWeakPtr()));
+#endif
 }
 
 void DeviceInfoSyncBridge::OnHardwareInfoRetrieved(
