--- media/capture/video/fake_video_capture_device_factory.cc.orig	2023-03-30 00:33:53 UTC
+++ media/capture/video/fake_video_capture_device_factory.cc
@@ -213,7 +213,7 @@ void FakeVideoCaptureDeviceFactory::GetDevicesInfo(
   int entry_index = 0;
   for (const auto& entry : devices_config_) {
     VideoCaptureApi api =
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
         VideoCaptureApi::LINUX_V4L2_SINGLE_PLANE;
 #elif BUILDFLAG(IS_IOS)
         VideoCaptureApi::UNKNOWN;
