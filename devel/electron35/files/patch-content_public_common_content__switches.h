--- content/public/common/content_switches.h.orig	2025-03-24 20:50:14 UTC
+++ content/public/common/content_switches.h
@@ -108,6 +108,7 @@ CONTENT_EXPORT extern const char kEnableLogging[];
 CONTENT_EXPORT extern const char kEnableIsolatedWebAppsInRenderer[];
 CONTENT_EXPORT extern const char kEnableLCDText[];
 CONTENT_EXPORT extern const char kEnableLogging[];
+CONTENT_EXPORT extern const char kDisableUnveil[];
 CONTENT_EXPORT extern const char kEnableNetworkInformationDownlinkMax[];
 CONTENT_EXPORT extern const char kEnableCanvas2DLayers[];
 CONTENT_EXPORT extern const char kEnablePluginPlaceholderTesting[];
@@ -262,7 +263,7 @@ CONTENT_EXPORT extern const char kPreventResizingConte
 CONTENT_EXPORT extern const char kPreventResizingContentsForTesting[];
 #endif
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 CONTENT_EXPORT extern const char kEnableSpeechDispatcher[];
 #endif
 
