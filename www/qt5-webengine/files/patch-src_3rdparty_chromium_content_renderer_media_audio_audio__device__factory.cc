--- src/3rdparty/chromium/content/renderer/media/audio/audio_device_factory.cc.orig	2020-11-07 01:22:36 UTC
+++ src/3rdparty/chromium/content/renderer/media/audio/audio_device_factory.cc
@@ -36,7 +36,7 @@ AudioDeviceFactory* AudioDeviceFactory::factory_ = nul
 namespace {
 
 #if defined(OS_WIN) || defined(OS_MACOSX) || \
-    (defined(OS_LINUX) && !defined(OS_CHROMEOS))
+    (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
 // Due to driver deadlock issues on Windows (http://crbug/422522) there is a
 // chance device authorization response is never received from the browser side.
 // In this case we will time out, to avoid renderer hang forever waiting for
