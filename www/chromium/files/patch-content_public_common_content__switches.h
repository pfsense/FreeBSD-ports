--- content/public/common/content_switches.h.orig	2020-03-16 18:40:32 UTC
+++ content/public/common/content_switches.h
@@ -278,7 +278,7 @@ CONTENT_EXPORT extern const char kRemoteDebuggingSocke
 CONTENT_EXPORT extern const char kRendererWaitForJavaDebugger[];
 #endif
 
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_FREEBSD)
 CONTENT_EXPORT extern const char kEnableSpeechDispatcher[];
 #endif
 
