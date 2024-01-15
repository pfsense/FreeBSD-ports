--- ui/views/window/dialog_delegate.cc.orig	2023-03-30 00:34:19 UTC
+++ ui/views/window/dialog_delegate.cc
@@ -73,7 +73,7 @@ Widget* DialogDelegate::CreateDialogWidget(
 
 // static
 bool DialogDelegate::CanSupportCustomFrame(gfx::NativeView parent) {
-#if (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)) && \
+#if (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)) && \
     BUILDFLAG(ENABLE_DESKTOP_AURA)
   // The new style doesn't support unparented dialogs on Linux desktop.
   return parent != nullptr;
