--- third_party/blink/renderer/modules/webgpu/gpu_canvas_context.cc.orig	2025-04-22 20:15:27 UTC
+++ third_party/blink/renderer/modules/webgpu/gpu_canvas_context.cc
@@ -207,7 +207,7 @@ bool GPUCanvasContext::PaintRenderingResultsToCanvas(
 
   scoped_refptr<WebGPUMailboxTexture> front_buffer_texture;
   if (source_buffer == kFrontBuffer) {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
     // By returning false here the canvas will show up as black in the scenarios
     // that copy the front buffer, such as printing.
     // TODO(crbug.com/40902474): Support concurrent SharedImage reads via Dawn
