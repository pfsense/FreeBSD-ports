--- chrome/browser/chrome_browser_interface_binders.cc.orig	2025-06-19 07:37:57 UTC
+++ chrome/browser/chrome_browser_interface_binders.cc
@@ -81,7 +81,7 @@
 #endif  // BUILDFLAG(ENABLE_UNHANDLED_TAP)
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/screen_ai/screen_ai_service_router.h"
 #include "chrome/browser/screen_ai/screen_ai_service_router_factory.h"
 #include "chrome/browser/ui/web_applications/sub_apps_service_impl.h"
@@ -363,7 +363,7 @@ void BindMediaFoundationPreferences(
 #endif  // BUILDFLAG(IS_WIN)
 
 #if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || \
-    BUILDFLAG(IS_WIN)
+    BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
 void BindScreenAIAnnotator(
     content::RenderFrameHost* frame_host,
     mojo::PendingReceiver<screen_ai::mojom::ScreenAIAnnotator> receiver) {
@@ -520,7 +520,7 @@ void PopulateChromeFrameBinders(
 #endif  // BUILDFLAG(ENABLE_SPEECH_SERVICE)
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   if (base::FeatureList::IsEnabled(blink::features::kDesktopPWAsSubApps) &&
       !render_frame_host->GetParentOrOuterDocument()) {
     // The service binder will reject non-primary main frames, but we still need
