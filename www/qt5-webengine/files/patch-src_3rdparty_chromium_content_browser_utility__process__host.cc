--- src/3rdparty/chromium/content/browser/utility_process_host.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/content/browser/utility_process_host.cc
@@ -59,7 +59,7 @@ UtilityProcessHost::UtilityProcessHost(std::unique_ptr
 
 UtilityProcessHost::UtilityProcessHost(std::unique_ptr<Client> client)
     : sandbox_type_(sandbox::policy::SandboxType::kUtility),
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
       child_flags_(ChildProcessHost::CHILD_ALLOW_SELF),
 #else
       child_flags_(ChildProcessHost::CHILD_NORMAL),
