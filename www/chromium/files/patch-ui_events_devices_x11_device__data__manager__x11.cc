--- ui/events/devices/x11/device_data_manager_x11.cc.orig	2025-07-02 06:08:04 UTC
+++ ui/events/devices/x11/device_data_manager_x11.cc
@@ -855,6 +855,7 @@ void DeviceDataManagerX11::SetDisabledKeyboardAllowedK
 }
 
 void DeviceDataManagerX11::DisableDevice(x11::Input::DeviceId deviceid) {
+  NOTIMPLEMENTED();
   blocked_devices_.set(static_cast<uint32_t>(deviceid), true);
   // TODO(rsadam@): Support blocking touchscreen devices.
   std::vector<KeyboardDevice> keyboards = GetKeyboardDevices();
