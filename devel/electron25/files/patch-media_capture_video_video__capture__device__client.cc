--- media/capture/video/video_capture_device_client.cc.orig	2023-05-25 00:41:58 UTC
+++ media/capture/video/video_capture_device_client.cc
@@ -321,7 +321,7 @@ void VideoCaptureDeviceClient::OnIncomingCapturedData(
 // see http://linuxtv.org/downloads/v4l-dvb-apis/packed-rgb.html.
 // Windows RGB24 defines blue at lowest byte,
 // see https://msdn.microsoft.com/en-us/library/windows/desktop/dd407253
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
       fourcc_format = libyuv::FOURCC_RAW;
 #elif BUILDFLAG(IS_WIN)
       fourcc_format = libyuv::FOURCC_24BG;
