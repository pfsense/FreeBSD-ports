--- third_party/blink/renderer/controller/memory_usage_monitor_posix.cc.orig	2021-01-07 00:36:41 UTC
+++ third_party/blink/renderer/controller/memory_usage_monitor_posix.cc
@@ -134,7 +134,7 @@ void MemoryUsageMonitorPosix::SetProcFiles(base::File 
   status_fd_.reset(status_file.TakePlatformFile());
 }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 // static
 void MemoryUsageMonitorPosix::Bind(
     mojo::PendingReceiver<mojom::blink::MemoryUsageMonitorLinux> receiver) {
