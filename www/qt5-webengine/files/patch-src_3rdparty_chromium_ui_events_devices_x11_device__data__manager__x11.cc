--- src/3rdparty/chromium/ui/events/devices/x11/device_data_manager_x11.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/events/devices/x11/device_data_manager_x11.cc
@@ -841,6 +841,9 @@ void DeviceDataManagerX11::DisableDevice(x11::Input::D
 }
 
 void DeviceDataManagerX11::DisableDevice(x11::Input::DeviceId deviceid) {
+#if defined(OS_BSD)
+  NOTIMPLEMENTED();
+#else
   blocked_devices_.set(static_cast<uint32_t>(deviceid), true);
   // TODO(rsadam@): Support blocking touchscreen devices.
   std::vector<InputDevice> keyboards = GetKeyboardDevices();
@@ -850,6 +853,7 @@ void DeviceDataManagerX11::DisableDevice(x11::Input::D
     keyboards.erase(it);
     DeviceDataManager::OnKeyboardDevicesUpdated(keyboards);
   }
+#endif
 }
 
 void DeviceDataManagerX11::EnableDevice(x11::Input::DeviceId deviceid) {
