--- third_party/perfetto/src/tracing/core/tracing_service_impl.cc.orig	2021-07-15 19:15:51 UTC
+++ third_party/perfetto/src/tracing/core/tracing_service_impl.cc
@@ -42,7 +42,8 @@
 
 #if PERFETTO_BUILDFLAG(PERFETTO_OS_ANDROID) || \
     PERFETTO_BUILDFLAG(PERFETTO_OS_LINUX) ||   \
-    PERFETTO_BUILDFLAG(PERFETTO_OS_APPLE)
+    PERFETTO_BUILDFLAG(PERFETTO_OS_APPLE) ||   \
+    PERFETTO_BUILDFLAG(PERFETTO_OS_FREEBSD)
 #define PERFETTO_HAS_CHMOD
 #include <sys/stat.h>
 #endif
@@ -2743,8 +2744,9 @@ bool TracingServiceImpl::SnapshotClocks(
 
   TracingSession::ClockSnapshotData new_snapshot_data;
 
-#if !PERFETTO_BUILDFLAG(PERFETTO_OS_APPLE) && \
-    !PERFETTO_BUILDFLAG(PERFETTO_OS_WIN) &&   \
+#if !PERFETTO_BUILDFLAG(PERFETTO_OS_APPLE) &&   \
+    !PERFETTO_BUILDFLAG(PERFETTO_OS_WIN) &&     \
+    !PERFETTO_BUILDFLAG(PERFETTO_OS_FREEBSD) && \
     !PERFETTO_BUILDFLAG(PERFETTO_OS_NACL)
   struct {
     clockid_t id;
