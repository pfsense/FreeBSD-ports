--- src/3rdparty/chromium/content/browser/webui/web_ui_main_frame_observer.cc.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/content/browser/webui/web_ui_main_frame_observer.cc
@@ -13,7 +13,7 @@
 #include "content/public/browser/navigation_handle.h"
 #include "content/public/browser/web_ui_controller.h"
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "base/callback_helpers.h"
 #include "base/feature_list.h"
 #include "base/logging.h"
@@ -31,7 +31,7 @@ namespace {
 
 namespace {
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // Remove the pieces of the URL we don't want to send back with the error
 // reports. In particular, do not send query or fragments as those can have
 // privacy-sensitive information in them.
@@ -55,7 +55,7 @@ WebUIMainFrameObserver::~WebUIMainFrameObserver() = de
 
 WebUIMainFrameObserver::~WebUIMainFrameObserver() = default;
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 void WebUIMainFrameObserver::OnDidAddMessageToConsole(
     RenderFrameHost* source_frame,
     blink::mojom::ConsoleMessageLevel log_level,
@@ -163,7 +163,7 @@ void WebUIMainFrameObserver::ReadyToCommitNavigation(
 
 // TODO(crbug.com/1129544) This is currently disabled due to Windows DLL
 // thunking issues. Fix & re-enable.
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   MaybeEnableWebUIJavaScriptErrorReporting(navigation_handle);
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
 }
