--- chrome/browser/ui/views/frame/opaque_browser_frame_view.cc.orig	2019-09-10 11:13:44 UTC
+++ chrome/browser/ui/views/frame/opaque_browser_frame_view.cc
@@ -47,7 +47,7 @@
 #include "ui/views/window/vector_icons/vector_icons.h"
 #include "ui/views/window/window_shape.h"
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 #include "ui/views/controls/menu/menu_runner.h"
 #endif
 
@@ -353,7 +353,7 @@ void OpaqueBrowserFrameView::ButtonPressed(views::Butt
 void OpaqueBrowserFrameView::OnMenuButtonClicked(views::Button* source,
                                                  const gfx::Point& point,
                                                  const ui::Event* event) {
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   views::MenuRunner menu_runner(frame()->GetSystemMenuModel(),
                                 views::MenuRunner::HAS_MNEMONICS);
   menu_runner.RunMenuAt(
@@ -479,7 +479,7 @@ bool OpaqueBrowserFrameView::EverHasVisibleBackgroundT
 
 OpaqueBrowserFrameView::FrameButtonStyle
 OpaqueBrowserFrameView::GetFrameButtonStyle() const {
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   return FrameButtonStyle::kMdButton;
 #else
   return FrameButtonStyle::kImageButton;
