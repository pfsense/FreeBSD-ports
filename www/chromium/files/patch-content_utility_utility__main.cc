--- content/utility/utility_main.cc.orig	2023-07-16 15:47:57 UTC
+++ content/utility/utility_main.cc
@@ -31,7 +31,7 @@
 #include "third_party/icu/source/common/unicode/unistr.h"
 #include "third_party/icu/source/i18n/unicode/timezone.h"
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "base/file_descriptor_store.h"
 #include "base/files/file_util.h"
 #include "base/pickle.h"
@@ -39,7 +39,9 @@
 #include "content/utility/speech/speech_recognition_sandbox_hook_linux.h"
 #include "gpu/config/gpu_info_collector.h"
 #include "media/gpu/sandbox/hardware_video_encoding_sandbox_hook_linux.h"
+#if !BUILDFLAG(IS_BSD)
 #include "sandbox/policy/linux/sandbox_linux.h"
+#endif
 #include "services/audio/audio_sandbox_hook_linux.h"
 #include "services/network/network_sandbox_hook_linux.h"
 // gn check is not smart enough to realize that this include only applies to
@@ -51,10 +53,14 @@
 #endif
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH) || BUILDFLAG(IS_BSD)
 #include "media/gpu/sandbox/hardware_video_decoding_sandbox_hook_linux.h"
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH)
 
+#if BUILDFLAG(IS_BSD)
+#include "sandbox/policy/sandbox.h"
+#endif
+
 #if BUILDFLAG(IS_CHROMEOS_ASH)
 #include "chromeos/ash/components/assistant/buildflags.h"
 #include "chromeos/ash/services/ime/ime_sandbox_hook.h"
@@ -66,7 +72,7 @@
 #endif  // BUILDFLAG(IS_CHROMEOS_ASH)
 
 #if (BUILDFLAG(ENABLE_SCREEN_AI_SERVICE) && \
-     (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)))
+     (BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)))
 #include "components/services/screen_ai/sandbox/screen_ai_sandbox_hook_linux.h"  // nogncheck
 #endif
 
@@ -91,7 +97,7 @@ namespace content {
 
 namespace {
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 std::vector<std::string> GetNetworkContextsParentDirectories() {
   base::MemoryMappedFile::Region region;
   base::ScopedFD read_pipe_fd = base::FileDescriptorStore::GetInstance().TakeFD(
@@ -119,7 +125,7 @@ std::vector<std::string> GetNetworkContextsParentDirec
 
 bool ShouldUseAmdGpuPolicy(sandbox::mojom::Sandbox sandbox_type) {
   const bool obtain_gpu_info =
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH) || BUILDFLAG(IS_BSD)
       sandbox_type == sandbox::mojom::Sandbox::kHardwareVideoDecoding ||
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH)
       sandbox_type == sandbox::mojom::Sandbox::kHardwareVideoEncoding;
@@ -235,7 +241,7 @@ int UtilityMain(MainFunctionParams parameters) {
     }
   }
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   // Initializes the sandbox before any threads are created.
   // TODO(jorgelo): move this after GTK initialization when we enable a strict
   // Seccomp-BPF policy.
@@ -264,7 +270,7 @@ int UtilityMain(MainFunctionParams parameters) {
       pre_sandbox_hook = base::BindOnce(&screen_ai::ScreenAIPreSandboxHook);
       break;
 #endif
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_ASH) || BUILDFLAG(IS_BSD)
     case sandbox::mojom::Sandbox::kHardwareVideoDecoding:
       pre_sandbox_hook =
           base::BindOnce(&media::HardwareVideoDecodingPreSandboxHook);
@@ -291,6 +297,7 @@ int UtilityMain(MainFunctionParams parameters) {
     default:
       break;
   }
+#if !BUILDFLAG(IS_BSD)
   if (!sandbox::policy::IsUnsandboxedSandboxType(sandbox_type) &&
       (parameters.zygote_child || !pre_sandbox_hook.is_null())) {
     sandbox::policy::SandboxLinux::Options sandbox_options;
@@ -299,6 +306,11 @@ int UtilityMain(MainFunctionParams parameters) {
     sandbox::policy::Sandbox::Initialize(
         sandbox_type, std::move(pre_sandbox_hook), sandbox_options);
   }
+#else
+  sandbox::policy::Sandbox::Initialize(
+      sandbox_type, std::move(pre_sandbox_hook),
+      sandbox::policy::SandboxLinux::Options());
+#endif
 #elif BUILDFLAG(IS_WIN)
   g_utility_target_services = parameters.sandbox_info->target_services;
 
