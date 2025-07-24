--- remoting/host/base/desktop_environment_options.cc.orig	2025-05-07 06:48:23 UTC
+++ remoting/host/base/desktop_environment_options.cc
@@ -109,7 +109,7 @@ bool DesktopEnvironmentOptions::capture_video_on_dedic
   // TODO(joedow): Determine whether we can migrate additional platforms to
   // using the DesktopCaptureWrapper instead of the DesktopCaptureProxy. Then
   // clean up DesktopCapturerProxy::Core::CreateCapturer().
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return capture_video_on_dedicated_thread_;
 #else
   return false;
