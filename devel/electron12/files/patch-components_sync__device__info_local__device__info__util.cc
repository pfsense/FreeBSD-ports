--- components/sync_device_info/local_device_info_util.cc.orig	2021-04-14 01:08:47 UTC
+++ components/sync_device_info/local_device_info_util.cc
@@ -53,7 +53,7 @@ std::string GetPersonalizableDeviceNameInternal();
 sync_pb::SyncEnums::DeviceType GetLocalDeviceType() {
 #if BUILDFLAG(IS_CHROMEOS_ASH)
   return sync_pb::SyncEnums_DeviceType_TYPE_CROS;
-#elif defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#elif defined(OS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || defined(OS_BSD)
   return sync_pb::SyncEnums_DeviceType_TYPE_LINUX;
 #elif defined(OS_ANDROID) || defined(OS_IOS)
   return ui::GetDeviceFormFactor() == ui::DEVICE_FORM_FACTOR_TABLET
