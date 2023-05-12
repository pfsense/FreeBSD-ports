--- third_party/blink/renderer/modules/webgpu/gpu_queue.cc.orig	2023-03-30 00:33:58 UTC
+++ third_party/blink/renderer/modules/webgpu/gpu_queue.cc
@@ -707,7 +707,7 @@ bool GPUQueue::CopyFromCanvasSourceImage(
 // platform requires interop supported. According to the bug, this change will
 // be a long time task. So disable using webgpu mailbox texture uploading path
 // on linux platform.
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   use_webgpu_mailbox_texture = false;
   unaccelerated_image = image->MakeUnaccelerated();
   image = unaccelerated_image.get();
