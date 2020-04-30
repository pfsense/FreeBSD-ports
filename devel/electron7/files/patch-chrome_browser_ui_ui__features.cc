--- chrome/browser/ui/ui_features.cc.orig	2019-12-12 12:39:17 UTC
+++ chrome/browser/ui/ui_features.cc
@@ -81,7 +81,7 @@ const base::Feature kWebFooterExperiment{"WebFooterExp
 const base::Feature kWebUITabStrip{"WebUITabStrip",
                                    base::FEATURE_DISABLED_BY_DEFAULT};
 
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
 constexpr base::Feature kEnableDbusAndX11StatusIcons{
     "EnableDbusAndX11StatusIcons", base::FEATURE_ENABLED_BY_DEFAULT};
 #endif
