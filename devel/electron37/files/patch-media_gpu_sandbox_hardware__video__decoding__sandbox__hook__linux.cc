--- media/gpu/sandbox/hardware_video_decoding_sandbox_hook_linux.cc.orig	2025-04-22 20:15:27 UTC
+++ media/gpu/sandbox/hardware_video_decoding_sandbox_hook_linux.cc
@@ -17,7 +17,9 @@
 #include "media/gpu/vaapi/vaapi_wrapper.h"
 #endif
 
+#if !BUILDFLAG(IS_BSD)
 using sandbox::syscall_broker::BrokerFilePermission;
+#endif
 
 // TODO(b/195769334): the hardware video decoding sandbox is really only useful
 // when building with VA-API or V4L2 (otherwise, we're not really doing hardware
@@ -33,6 +35,7 @@ namespace {
 namespace media {
 namespace {
 
+#if !BUILDFLAG(IS_BSD)
 void AllowAccessToRenderNodes(std::vector<BrokerFilePermission>& permissions,
                               bool include_sys_dev_char,
                               bool read_write) {
@@ -189,6 +192,7 @@ bool HardwareVideoDecodingPreSandboxHookForV4L2(
   NOTREACHED();
 #endif  // BUILDFLAG(USE_V4L2_CODEC)
 }
+#endif
 
 }  // namespace
 
@@ -204,6 +208,7 @@ bool HardwareVideoDecodingPreSandboxHook(
 //   (at least).
 bool HardwareVideoDecodingPreSandboxHook(
     sandbox::policy::SandboxLinux::Options options) {
+#if !BUILDFLAG(IS_BSD)
   using HardwareVideoDecodingProcessPolicy =
       sandbox::policy::HardwareVideoDecodingProcessPolicy;
   using PolicyType =
@@ -249,6 +254,7 @@ bool HardwareVideoDecodingPreSandboxHook(
   // |permissions| is empty?
   sandbox::policy::SandboxLinux::GetInstance()->StartBrokerProcess(
       command_set, permissions, options);
+#endif
   return true;
 }
 
