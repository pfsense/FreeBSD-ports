--- base/process/internal_linux.cc.orig	2021-01-07 00:36:18 UTC
+++ base/process/internal_linux.cc
@@ -30,7 +30,11 @@ namespace internal {
 
 const char kProcDir[] = "/proc";
 
+#if defined(OS_BSD)
+const char kStatFile[] = "status";
+#else
 const char kStatFile[] = "stat";
+#endif
 
 FilePath GetProcPidDir(pid_t pid) {
   return FilePath(kProcDir).Append(NumberToString(pid));
@@ -66,6 +70,7 @@ bool ReadProcFile(const FilePath& file, std::string* b
     DLOG(WARNING) << "Failed to read " << file.MaybeAsASCII();
     return false;
   }
+
   return !buffer->empty();
 }
 
@@ -81,6 +86,22 @@ bool ParseProcStats(const std::string& stats_data,
   if (stats_data.empty())
     return false;
 
+#if defined(OS_BSD)
+  proc_stats->clear();
+
+  std::vector<std::string> other_stats = SplitString(
+      stats_data, " ", base::TRIM_WHITESPACE, base::SPLIT_WANT_ALL);
+
+  for (const auto& i : other_stats) {
+    auto pos = i.find(',');
+
+    if (pos == std::string::npos) {
+      proc_stats->push_back(i);
+    } else {
+      proc_stats->push_back(i.substr(0, pos));
+    }
+  }
+#else
   // The stat file is formatted as:
   // pid (process name) data1 data2 .... dataN
   // Look for the closing paren by scanning backwards, to avoid being fooled by
@@ -110,6 +131,7 @@ bool ParseProcStats(const std::string& stats_data,
       base::TRIM_WHITESPACE, base::SPLIT_WANT_ALL);
   for (const auto& i : other_stats)
     proc_stats->push_back(i);
+#endif
   return true;
 }
 
@@ -157,7 +179,11 @@ int64_t ReadProcStatsAndGetFieldAsInt64(pid_t pid, Pro
 }
 
 int64_t ReadProcSelfStatsAndGetFieldAsInt64(ProcStatsFields field_num) {
+#if defined(OS_BSD)
+  FilePath stat_file = FilePath(kProcDir).Append("curproc").Append(kStatFile);
+#else
   FilePath stat_file = FilePath(kProcDir).Append("self").Append(kStatFile);
+#endif
   return ReadStatFileAndGetFieldAsInt64(stat_file, field_num);
 }
 
@@ -173,6 +199,9 @@ size_t ReadProcStatsAndGetFieldAsSizeT(pid_t pid,
 }
 
 Time GetBootTime() {
+#if defined(OS_BSD)
+  return Time();
+#else
   FilePath path("/proc/stat");
   std::string contents;
   if (!ReadProcFile(path, &contents))
@@ -186,9 +215,13 @@ Time GetBootTime() {
   if (!StringToInt(btime_it->second, &btime))
     return Time();
   return Time::FromTimeT(btime);
+#endif
 }
 
 TimeDelta GetUserCpuTimeSinceBoot() {
+#if defined(OS_BSD)
+  return TimeDelta();
+#else
   FilePath path("/proc/stat");
   std::string contents;
   if (!ReadProcFile(path, &contents))
@@ -212,6 +245,7 @@ TimeDelta GetUserCpuTimeSinceBoot() {
     return TimeDelta();
 
   return ClockTicksToTimeDelta(user + nice);
+#endif
 }
 
 TimeDelta ClockTicksToTimeDelta(int clock_ticks) {
