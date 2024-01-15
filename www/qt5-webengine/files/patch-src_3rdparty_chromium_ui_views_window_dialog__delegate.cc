--- src/3rdparty/chromium/ui/views/window/dialog_delegate.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/views/window/dialog_delegate.cc
@@ -71,7 +71,7 @@ bool DialogDelegate::CanSupportCustomFrame(gfx::Native
 
 // static
 bool DialogDelegate::CanSupportCustomFrame(gfx::NativeView parent) {
-#if (defined(OS_LINUX) || defined(OS_CHROMEOS)) && \
+#if (defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)) && \
     BUILDFLAG(ENABLE_DESKTOP_AURA)
   // The new style doesn't support unparented dialogs on Linux desktop.
   return parent != nullptr;
