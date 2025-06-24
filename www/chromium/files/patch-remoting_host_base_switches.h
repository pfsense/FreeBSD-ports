--- remoting/host/base/switches.h.orig	2025-05-28 14:55:43 UTC
+++ remoting/host/base/switches.h
@@ -35,13 +35,13 @@ extern const char kProcessTypeRdpDesktopSession[];
 extern const char kProcessTypeEvaluateCapability[];
 extern const char kProcessTypeFileChooser[];
 extern const char kProcessTypeUrlForwarderConfigurator[];
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 extern const char kProcessTypeXSessionChooser[];
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
 
 extern const char kEvaluateCapabilitySwitchName[];
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // Used to record client sessions to wtmpd.
 extern const char kEnableWtmpdb[];
 #endif
