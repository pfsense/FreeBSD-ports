--- services/device/time_zone_monitor/time_zone_monitor_linux.cc.orig	2022-03-28 18:11:04 UTC
+++ services/device/time_zone_monitor/time_zone_monitor_linux.cc
@@ -137,7 +137,11 @@ class TimeZoneMonitorLinuxImpl
     // false positives are harmless, assuming the false positive rate is
     // reasonable.
     const char* const kFilesToWatch[] = {
+#if defined(OS_BSD)
+        "/etc/localtime",
+#else
         "/etc/localtime", "/etc/timezone", "/etc/TZ",
+#endif
     };
     for (size_t index = 0; index < base::size(kFilesToWatch); ++index) {
       file_path_watchers_.push_back(std::make_unique<base::FilePathWatcher>());
