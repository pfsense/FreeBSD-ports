--- electron/shell/common/node_bindings.cc.orig	2023-08-22 15:28:54 UTC
+++ electron/shell/common/node_bindings.cc
@@ -38,7 +38,7 @@
 #include "third_party/blink/renderer/bindings/core/v8/v8_initializer.h"  // nogncheck
 #include "third_party/electron_node/src/debug_utils.h"
 
-#if !IS_MAS_BUILD()
+#if !IS_MAS_BUILD() && !BUILDFLAG(IS_BSD)
 #include "shell/common/crash_keys.h"
 #endif
 
@@ -155,7 +155,7 @@ bool g_is_initialized = false;
 void V8FatalErrorCallback(const char* location, const char* message) {
   LOG(ERROR) << "Fatal error in V8: " << location << " " << message;
 
-#if !IS_MAS_BUILD()
+#if !IS_MAS_BUILD() && !BUILDFLAG(IS_BSD)
   electron::crash_keys::SetCrashKey("electron.v8-fatal.message", message);
   electron::crash_keys::SetCrashKey("electron.v8-fatal.location", location);
 #endif
@@ -436,7 +436,7 @@ void NodeBindings::Initialize(v8::Local<v8::Context> c
   TRACE_EVENT0("electron", "NodeBindings::Initialize");
   // Open node's error reporting system for browser process.
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // Get real command line in renderer process forked by zygote.
   if (browser_env_ != BrowserEnvironment::kBrowser)
     ElectronCommandLine::InitializeFromCommandLine();
