--- ui/views/widget/desktop_aura/desktop_window_tree_host_platform.cc.orig	2021-07-15 19:14:09 UTC
+++ ui/views/widget/desktop_aura/desktop_window_tree_host_platform.cc
@@ -851,7 +851,7 @@ bool DesktopWindowTreeHostPlatform::ShouldUseLayerForS
 // DesktopWindowTreeHost:
 
 // Linux subclasses this host and adds some Linux specific bits.
-#if !defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if !defined(OS_LINUX) && !defined(OS_CHROMEOS) && !defined(OS_BSD)
 // static
 DesktopWindowTreeHost* DesktopWindowTreeHost::Create(
     internal::NativeWidgetDelegate* native_widget_delegate,
