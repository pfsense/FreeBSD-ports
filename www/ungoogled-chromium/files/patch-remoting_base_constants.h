--- remoting/base/constants.h.orig	2025-05-31 17:16:41 UTC
+++ remoting/base/constants.h
@@ -27,7 +27,7 @@ const int kDefaultDpi = 96;
 // The video frame rate.
 constexpr int kTargetFrameRate = 30;
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 inline constexpr char kChromeRemoteDesktopSessionEnvVar[] =
     "CHROME_REMOTE_DESKTOP_SESSION";
 #endif
