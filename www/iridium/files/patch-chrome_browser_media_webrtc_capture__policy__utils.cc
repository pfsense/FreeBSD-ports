--- chrome/browser/media/webrtc/capture_policy_utils.cc.orig	2023-07-24 14:27:53 UTC
+++ chrome/browser/media/webrtc/capture_policy_utils.cc
@@ -124,7 +124,7 @@ AllowedScreenCaptureLevel GetAllowedCaptureLevel(const
 }
 
 bool IsGetAllScreensMediaAllowedForAnySite(content::BrowserContext* context) {
-#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   Profile* profile = Profile::FromBrowserContext(context);
   if (!profile) {
     return false;
@@ -160,7 +160,7 @@ bool IsGetAllScreensMediaAllowedForAnySite(content::Br
 
 bool IsGetAllScreensMediaAllowed(content::BrowserContext* context,
                                  const GURL& url) {
-#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   Profile* profile = Profile::FromBrowserContext(context);
   if (!profile) {
     return false;
