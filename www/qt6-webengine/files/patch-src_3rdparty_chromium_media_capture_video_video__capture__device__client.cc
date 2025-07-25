--- src/3rdparty/chromium/media/capture/video/video_capture_device_client.cc.orig	2024-09-30 07:45:04 UTC
+++ src/3rdparty/chromium/media/capture/video/video_capture_device_client.cc
@@ -169,7 +169,7 @@ FourccAndFlip GetFourccAndFlipFromPixelFormat(
       CHECK(!is_width_odd && !is_height_odd);
       return {libyuv::FOURCC_UYVY};
     case media::PIXEL_FORMAT_RGB24:
-      if constexpr (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)) {
+      if constexpr (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)) {
         // Linux RGB24 defines red at lowest byte address,
         // see http://linuxtv.org/downloads/v4l-dvb-apis/packed-rgb.html.
         return {libyuv::FOURCC_RAW};
