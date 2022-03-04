--- src/3rdparty/chromium/media/capture/video/file_video_capture_device_factory.cc.orig	2018-11-13 18:25:11 UTC
+++ src/3rdparty/chromium/media/capture/video/file_video_capture_device_factory.cc
@@ -53,7 +53,7 @@ void FileVideoCaptureDeviceFactory::GetDeviceDescripto
 #elif defined(OS_MACOSX)
       command_line_file_path.value(), kFileVideoCaptureDeviceName,
       VideoCaptureApi::MACOSX_AVFOUNDATION
-#elif defined(OS_LINUX)
+#elif defined(OS_LINUX) || defined(OS_BSD)
       command_line_file_path.value(), kFileVideoCaptureDeviceName,
       VideoCaptureApi::LINUX_V4L2_SINGLE_PLANE
 #else
