--- media/audio/pulse/pulse_util.cc.orig	2025-09-06 10:01:20 UTC
+++ media/audio/pulse/pulse_util.cc
@@ -45,7 +45,11 @@ constexpr char kBrowserDisplayName[] = "chromium-brows
 
 #if defined(DLOPEN_PULSEAUDIO)
 static const base::FilePath::CharType kPulseLib[] =
+#if BUILDFLAG(IS_BSD)
+    FILE_PATH_LITERAL("libpulse.so");
+#else
     FILE_PATH_LITERAL("libpulse.so.0");
+#endif
 #endif
 
 void DestroyMainloop(pa_threaded_mainloop* mainloop) {
