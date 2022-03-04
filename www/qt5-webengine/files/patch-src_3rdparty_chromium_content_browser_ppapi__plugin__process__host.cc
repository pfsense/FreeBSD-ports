--- src/3rdparty/chromium/content/browser/ppapi_plugin_process_host.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/content/browser/ppapi_plugin_process_host.cc
@@ -359,7 +359,7 @@ bool PpapiPluginProcessHost::Init(const PepperPluginIn
   base::CommandLine::StringType plugin_launcher =
       browser_command_line.GetSwitchValueNative(switches::kPpapiPluginLauncher);
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   int flags = plugin_launcher.empty() ? ChildProcessHost::CHILD_ALLOW_SELF :
                                         ChildProcessHost::CHILD_NORMAL;
 #elif defined(OS_MACOSX)
