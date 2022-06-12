--- third_party/perfetto/src/base/test/vm_test_utils.cc.orig	2022-03-28 18:11:04 UTC
+++ third_party/perfetto/src/base/test/vm_test_utils.cc
@@ -92,7 +92,7 @@ bool IsMapped(void* start, size_t size) {
   // Fuchsia doesn't yet support paging (b/119503290).
   ignore_result(page_size);
   return true;
-#elif PERFETTO_BUILDFLAG(PERFETTO_OS_NACL)
+#elif PERFETTO_BUILDFLAG(PERFETTO_OS_NACL) || PERFETTO_BUILDFLAG(PERFETTO_OS_BSD)
   // mincore isn't available on NaCL.
   ignore_result(page_size);
   return true;
