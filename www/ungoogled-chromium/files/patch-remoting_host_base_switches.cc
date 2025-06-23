--- remoting/host/base/switches.cc.orig	2025-05-31 17:16:41 UTC
+++ remoting/host/base/switches.cc
@@ -23,13 +23,13 @@ const char kProcessTypeEvaluateCapability[] = "evaluat
 const char kProcessTypeFileChooser[] = "file_chooser";
 const char kProcessTypeUrlForwarderConfigurator[] =
     "url_forwarder_configurator";
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 const char kProcessTypeXSessionChooser[] = "xsession_chooser";
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
 
 const char kEvaluateCapabilitySwitchName[] = "evaluate-type";
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 const char kEnableWtmpdb[] = "enable-wtmpdb";
 #endif
 
