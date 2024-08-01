--- src/3rdparty/chromium/gpu/command_buffer/service/gles2_cmd_decoder.cc.orig	2023-12-12 22:08:45 UTC
+++ src/3rdparty/chromium/gpu/command_buffer/service/gles2_cmd_decoder.cc
@@ -2982,7 +2982,7 @@ GLES2Decoder* GLES2Decoder::Create(
 // Fix for Qt WebEngine Crash when QSG_RHI_BACKEND=opengl
 // Allow linux to run fuzzers.
 #if BUILDFLAG(ENABLE_VALIDATING_COMMAND_DECODER) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_WIN)
+    BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
   return new GLES2DecoderImpl(client, command_buffer_service, outputter, group);
 #else
   LOG(FATAL) << "Validating command decoder is not supported.";
