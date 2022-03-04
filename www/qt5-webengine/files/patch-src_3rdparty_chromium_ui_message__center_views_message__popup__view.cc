--- src/3rdparty/chromium/ui/message_center/views/message_popup_view.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/ui/message_center/views/message_popup_view.cc
@@ -106,7 +106,7 @@ void MessagePopupView::AutoCollapse() {
 void MessagePopupView::Show() {
   views::Widget::InitParams params(views::Widget::InitParams::TYPE_POPUP);
   params.z_order = ui::ZOrderLevel::kFloatingWindow;
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   // Make the widget explicitly activatable as TYPE_POPUP is not activatable by
   // default but we need focus for the inline reply textarea.
   params.activatable = views::Widget::InitParams::ACTIVATABLE_YES;
