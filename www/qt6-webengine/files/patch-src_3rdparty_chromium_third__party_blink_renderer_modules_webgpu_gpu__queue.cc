--- src/3rdparty/chromium/third_party/blink/renderer/modules/webgpu/gpu_queue.cc.orig	2024-08-26 12:06:38 UTC
+++ src/3rdparty/chromium/third_party/blink/renderer/modules/webgpu/gpu_queue.cc
@@ -788,7 +788,7 @@ bool GPUQueue::CopyFromCanvasSourceImage(
 // on linux platform.
 // TODO(crbug.com/1424119): using a webgpu mailbox texture on the OpenGLES
 // backend is failing for unknown reasons.
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   bool forceReadback = true;
 #elif BUILDFLAG(IS_ANDROID)
   // TODO(crbug.com/dawn/1969): Some Android devices don't fail to copy from
