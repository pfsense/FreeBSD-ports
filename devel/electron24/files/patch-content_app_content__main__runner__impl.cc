--- content/app/content_main_runner_impl.cc.orig	2023-04-10 14:01:56 UTC
+++ content/app/content_main_runner_impl.cc
@@ -140,13 +140,13 @@
 #include "content/browser/posix_file_descriptor_info_impl.h"
 #include "content/public/common/content_descriptors.h"
 
-#if !BUILDFLAG(IS_MAC)
+#if !BUILDFLAG(IS_MAC) && !BUILDFLAG(IS_BSD)
 #include "content/public/common/zygote/zygote_fork_delegate_linux.h"
 #endif
 
 #endif  // BUILDFLAG(IS_POSIX) || BUILDFLAG(IS_FUCHSIA)
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "base/files/file_path_watcher_inotify.h"
 #include "base/native_library.h"
 #include "base/rand_util.h"
@@ -187,6 +187,10 @@
 #include "media/base/media_switches.h"
 #endif
 
+#if BUILDFLAG(IS_BSD)
+#include "base/system/sys_info.h"
+#endif
+
 #if BUILDFLAG(IS_ANDROID)
 #include "base/system/sys_info.h"
 #include "content/browser/android/battery_metrics.h"
@@ -375,7 +379,7 @@ void InitializeZygoteSandboxForBrowserProcess(
 }
 #endif  // BUILDFLAG(USE_ZYGOTE)
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 
 #if BUILDFLAG(ENABLE_PPAPI)
 // Loads the (native) libraries but does not initialize them (i.e., does not
@@ -411,15 +415,17 @@ void PreloadLibraryCdms() {
 }
 #endif  // BUILDFLAG(ENABLE_LIBRARY_CDMS)
 
-#if BUILDFLAG(USE_ZYGOTE)
+#if BUILDFLAG(USE_ZYGOTE) || BUILDFLAG(IS_BSD)
 void PreSandboxInit() {
   // Pre-acquire resources needed by BoringSSL. See
   // https://boringssl.googlesource.com/boringssl/+/HEAD/SANDBOXING.md
   CRYPTO_pre_sandbox_init();
 
+#if !BUILDFLAG(IS_BSD)
   // Pre-read /proc/sys/fs/inotify/max_user_watches so it doesn't have to be
   // allowed by the sandbox.
   base::GetMaxNumberOfInotifyWatches();
+#endif
 
 #if BUILDFLAG(ENABLE_PPAPI)
   // Ensure access to the Pepper plugins before the sandbox is turned on.
@@ -438,6 +444,11 @@ void PreSandboxInit() {
   }
 #endif
 
+#if BUILDFLAG(IS_BSD)
+  // "cache" the amount of physical memory before pledge(2)
+  base::SysInfo::AmountOfPhysicalMemoryMB();
+#endif
+
   // Set the android SkFontMgr for blink. We need to ensure this is done
   // before the sandbox is initialized to allow the font manager to access
   // font configuration files on disk.
@@ -625,7 +636,7 @@ int NO_STACK_PROTECTOR RunZygote(ContentMainDelegate* 
   delegate->ZygoteStarting(&zygote_fork_delegates);
   media::InitializeMediaLibrary();
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   PreSandboxInit();
 #endif
 
@@ -828,11 +839,10 @@ int ContentMainRunnerImpl::Initialize(ContentMainParam
              kFieldTrialDescriptor + base::GlobalDescriptors::kBaseDescriptor);
 #endif  // !BUILDFLAG(IS_ANDROID)
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_OPENBSD)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
   g_fds->Set(kCrashDumpSignal,
              kCrashDumpSignal + base::GlobalDescriptors::kBaseDescriptor);
-#endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) ||
-        // BUILDFLAG(IS_OPENBSD)
+#endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
 
 #endif  // !BUILDFLAG(IS_WIN)
 
@@ -1027,6 +1037,16 @@ int ContentMainRunnerImpl::Initialize(ContentMainParam
   }
 #endif
 
+#if BUILDFLAG(IS_BSD)
+  if (process_type.empty()) {
+    sandbox::policy::SandboxLinux::Options sandbox_options;
+    sandbox::policy::SandboxLinux::GetInstance()->InitializeSandbox(
+        sandbox::policy::SandboxTypeFromCommandLine(
+            *base::CommandLine::ForCurrentProcess()),
+        sandbox::policy::SandboxLinux::PreSandboxHook(), sandbox_options);
+  }
+#endif
+
   delegate_->SandboxInitialized(process_type);
 
 #if BUILDFLAG(USE_ZYGOTE)
@@ -1094,7 +1114,7 @@ int NO_STACK_PROTECTOR ContentMainRunnerImpl::Run() {
           ->ReconfigureAfterFeatureListInit(process_type);
     }
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
     // If dynamic Mojo Core is being used, ensure that it's loaded very early in
     // the child/zygote process, before any sandbox is initialized. The library
     // is not fully initialized with IPC support until a ChildProcess is later
@@ -1132,6 +1152,11 @@ int NO_STACK_PROTECTOR ContentMainRunnerImpl::Run() {
   content_main_params_.reset();
 
   RegisterMainThreadFactories();
+
+#if BUILDFLAG(IS_BSD)
+  if (!process_type.empty())
+    PreSandboxInit();
+#endif 
 
   if (process_type.empty())
     return RunBrowser(std::move(main_params), start_minimal_browser);
