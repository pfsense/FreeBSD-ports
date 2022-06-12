--- content/browser/memory/swap_metrics_driver_impl_linux.cc.orig	2022-05-11 07:00:38 UTC
+++ content/browser/memory/swap_metrics_driver_impl_linux.cc
@@ -44,9 +44,13 @@ SwapMetricsDriverImplLinux::~SwapMetricsDriverImplLinu
 SwapMetricsDriver::SwapMetricsUpdateResult
 SwapMetricsDriverImplLinux::UpdateMetricsInternal(base::TimeDelta interval) {
   base::VmStatInfo vmstat;
+#if !defined(OS_BSD)
   if (!base::GetVmStatInfo(&vmstat)) {
     return SwapMetricsDriver::SwapMetricsUpdateResult::kSwapMetricsUpdateFailed;
   }
+#else
+    return SwapMetricsDriver::SwapMetricsUpdateResult::kSwapMetricsUpdateFailed;
+#endif
 
   uint64_t in_counts = vmstat.pswpin - last_pswpin_;
   uint64_t out_counts = vmstat.pswpout - last_pswpout_;
