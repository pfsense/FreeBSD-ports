--- content/browser/renderer_host/render_widget_host_view_event_handler.cc.orig	2021-11-19 04:25:16 UTC
+++ content/browser/renderer_host/render_widget_host_view_event_handler.cc
@@ -600,7 +600,7 @@ bool RenderWidgetHostViewEventHandler::CanRendererHand
   if (event->type() == ui::ET_MOUSE_EXITED) {
     if (mouse_locked || selection_popup)
       return false;
-#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_WIN) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
     // Don't forward the mouse leave message which is received when the context
     // menu is displayed by the page. This confuses the page and causes state
     // changes.
