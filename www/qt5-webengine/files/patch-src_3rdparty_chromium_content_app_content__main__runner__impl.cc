--- src/3rdparty/chromium/content/app/content_main_runner_impl.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/content/app/content_main_runner_impl.cc
@@ -131,7 +131,7 @@
 
 #endif  // OS_POSIX || OS_FUCHSIA
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 #include "base/native_library.h"
 #include "base/rand_util.h"
 #include "content/public/common/zygote/sandbox_support_linux.h"
@@ -150,7 +150,7 @@
 #include "content/public/common/content_client.h"
 #endif
 
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 #if BUILDFLAG(USE_ZYGOTE_HANDLE)
 #include "content/browser/sandbox_host_linux.h"
@@ -308,7 +308,7 @@ void InitializeZygoteSandboxForBrowserProcess(
 }
 #endif  // BUILDFLAG(USE_ZYGOTE_HANDLE)
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 #if BUILDFLAG(ENABLE_PLUGINS)
 // Loads the (native) libraries but does not initialize them (i.e., does not
@@ -401,7 +401,7 @@ void PreSandboxInit() {
 }
 #endif  // BUILDFLAG(USE_ZYGOTE_HANDLE)
 
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 }  // namespace
 
@@ -464,7 +464,7 @@ int RunZygote(ContentMainDelegate* delegate) {
   delegate->ZygoteStarting(&zygote_fork_delegates);
   media::InitializeMediaLibrary();
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   PreSandboxInit();
 #endif
 
@@ -841,7 +841,7 @@ int ContentMainRunnerImpl::Run(bool start_service_mana
       delegate_->PostFieldTrialInitialization();
     }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
     // If dynamic Mojo Core is being used, ensure that it's loaded very early in
     // the child/zygote process, before any sandbox is initialized. The library
     // is not fully initialized with IPC support until a ChildProcess is later
@@ -851,7 +851,7 @@ int ContentMainRunnerImpl::Run(bool start_service_mana
       CHECK_EQ(mojo::LoadCoreLibrary(GetMojoCoreSharedLibraryPath()),
                MOJO_RESULT_OK);
     }
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   }
 
   MainFunctionParams main_params(command_line);
