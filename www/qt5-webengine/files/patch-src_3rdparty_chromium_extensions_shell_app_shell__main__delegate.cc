--- src/3rdparty/chromium/extensions/shell/app/shell_main_delegate.cc.orig	2019-11-27 21:12:25 UTC
+++ src/3rdparty/chromium/extensions/shell/app/shell_main_delegate.cc
@@ -38,7 +38,7 @@
 
 #if defined(OS_WIN)
 #include "base/base_paths_win.h"
-#elif defined(OS_LINUX)
+#elif defined(OS_LINUX) || defined(OS_BSD)
 #include "base/nix/xdg_util.h"
 #elif defined(OS_MACOSX)
 #include "base/base_paths_mac.h"
@@ -70,7 +70,7 @@ base::FilePath GetDataPath() {
     return cmd_line->GetSwitchValuePath(switches::kContentShellDataPath);
 
   base::FilePath data_dir;
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   std::unique_ptr<base::Environment> env(base::Environment::Create());
   data_dir = base::nix::GetXDGDirectory(
       env.get(), base::nix::kXdgConfigHomeEnvVar, base::nix::kDotConfigDir);
