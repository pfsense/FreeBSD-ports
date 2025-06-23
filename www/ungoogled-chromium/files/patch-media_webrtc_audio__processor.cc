--- media/webrtc/audio_processor.cc.orig	2025-05-31 17:16:41 UTC
+++ media/webrtc/audio_processor.cc
@@ -512,7 +512,7 @@ std::optional<double> AudioProcessor::ProcessData(
   // controller.
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC)
   DCHECK_LE(volume, 1.0);
-#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_OPENBSD)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // We have a special situation on Linux where the microphone volume can be
   // "higher than maximum". The input volume slider in the sound preference
   // allows the user to set a scaling that is higher than 100%. It means that
