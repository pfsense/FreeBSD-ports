--- third_party/perfetto/src/tracing/ipc/memfd.cc.orig	2023-09-17 07:59:53 UTC
+++ third_party/perfetto/src/tracing/ipc/memfd.cc
@@ -20,7 +20,8 @@
 
 #define PERFETTO_MEMFD_ENABLED()             \
   PERFETTO_BUILDFLAG(PERFETTO_OS_ANDROID) || \
-      PERFETTO_BUILDFLAG(PERFETTO_OS_LINUX)
+      (PERFETTO_BUILDFLAG(PERFETTO_OS_LINUX) && \
+      !PERFETTO_BUILDFLAG(PERFETTO_OS_BSD))
 
 #if PERFETTO_MEMFD_ENABLED()
 
