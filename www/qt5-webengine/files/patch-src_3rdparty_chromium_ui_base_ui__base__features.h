--- src/3rdparty/chromium/ui/base/ui_base_features.h.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/ui/base/ui_base_features.h
@@ -60,11 +60,11 @@ COMPONENT_EXPORT(UI_BASE_FEATURES) bool IsUsingWMPoint
 COMPONENT_EXPORT(UI_BASE_FEATURES) bool IsUsingWMPointerForTouch();
 #endif  // defined(OS_WIN)
 
-#if defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || \
+#if defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || defined(OS_BSD) || \
     defined(OS_CHROMEOS)
 COMPONENT_EXPORT(UI_BASE_FEATURES)
 extern const base::Feature kDirectManipulationStylus;
-#endif  // defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) ||
+#endif  // defined(OS_WIN) || defined(OS_APPLE) || defined(OS_LINUX) || defined(OS_BSD) ||
         // defined(OS_CHROMEOS)
 
 // Used to enable forced colors mode for web content.
