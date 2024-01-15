--- src/3rdparty/chromium/content/browser/renderer_host/render_widget_host_view_event_handler.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/content/browser/renderer_host/render_widget_host_view_event_handler.cc
@@ -716,7 +716,7 @@ bool RenderWidgetHostViewEventHandler::CanRendererHand
   if (event->type() == ui::ET_MOUSE_EXITED) {
     if (mouse_locked || selection_popup)
       return false;
-#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
     // Don't forward the mouse leave message which is received when the context
     // menu is displayed by the page. This confuses the page and causes state
     // changes.
