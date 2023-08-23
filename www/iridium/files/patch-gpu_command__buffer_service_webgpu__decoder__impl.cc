--- gpu/command_buffer/service/webgpu_decoder_impl.cc.orig	2023-07-24 14:27:53 UTC
+++ gpu/command_buffer/service/webgpu_decoder_impl.cc
@@ -1245,7 +1245,7 @@ void WebGPUDecoderImpl::RequestAdapterImpl(
 
   if (gr_context_type_ != GrContextType::kVulkan &&
       use_webgpu_adapter_ != WebGPUAdapterName::kOpenGLES) {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
     callback(WGPURequestAdapterStatus_Unavailable, nullptr,
              "WebGPU on Linux requires command-line flag "
              "--enable-features=Vulkan",
