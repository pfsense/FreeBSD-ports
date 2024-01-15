--- content/browser/utility_process_host.h.orig	2023-09-17 07:59:53 UTC
+++ content/browser/utility_process_host.h
@@ -39,7 +39,7 @@ namespace base {
 class Thread;
 }  // namespace base
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
 namespace viz {
 class GpuClient;
 }  // namespace viz
@@ -225,7 +225,7 @@ class CONTENT_EXPORT UtilityProcessHost
   std::vector<RunServiceDeprecatedCallback> pending_run_service_callbacks_;
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
   std::unique_ptr<viz::GpuClient, base::OnTaskRunnerDeleter> gpu_client_;
 #endif
 
