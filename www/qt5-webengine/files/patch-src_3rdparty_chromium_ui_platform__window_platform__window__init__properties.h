--- src/3rdparty/chromium/ui/platform_window/platform_window_init_properties.h.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/platform_window/platform_window_init_properties.h
@@ -41,7 +41,7 @@ class WorkspaceExtensionDelegate;
 
 class WorkspaceExtensionDelegate;
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 class X11ExtensionDelegate;
 #endif
 
@@ -82,7 +82,7 @@ struct COMPONENT_EXPORT(PLATFORM_WINDOW) PlatformWindo
 
   WorkspaceExtensionDelegate* workspace_extension_delegate = nullptr;
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   bool prefer_dark_theme = false;
   gfx::ImageSkia* icon = nullptr;
   base::Optional<int> background_color;
