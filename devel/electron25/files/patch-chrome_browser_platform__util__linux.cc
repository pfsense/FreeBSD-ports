--- chrome/browser/platform_util_linux.cc.orig	2023-03-30 00:33:43 UTC
+++ chrome/browser/platform_util_linux.cc
@@ -298,7 +298,9 @@ void RunCommand(const std::string& command,
 
   base::LaunchOptions options;
   options.current_directory = working_directory;
+#if !defined(OS_BSD)
   options.allow_new_privs = true;
+#endif
   // xdg-open can fall back on mailcap which eventually might plumb through
   // to a command that needs a terminal.  Set the environment variable telling
   // it that we definitely don't have a terminal available and that it should
