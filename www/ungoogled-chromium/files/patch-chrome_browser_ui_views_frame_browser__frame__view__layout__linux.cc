--- chrome/browser/ui/views/frame/browser_frame_view_layout_linux.cc.orig	2025-05-31 17:16:41 UTC
+++ chrome/browser/ui/views/frame/browser_frame_view_layout_linux.cc
@@ -55,7 +55,7 @@ gfx::Insets BrowserFrameViewLayoutLinux::RestoredFrame
         OpaqueBrowserFrameViewLayout::RestoredFrameBorderInsets());
   }
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   const bool tiled = delegate_->IsTiled();
 #else
   const bool tiled = false;
