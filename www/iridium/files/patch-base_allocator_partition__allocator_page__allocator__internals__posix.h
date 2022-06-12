--- base/allocator/partition_allocator/page_allocator_internals_posix.h.orig	2022-04-01 07:48:30 UTC
+++ base/allocator/partition_allocator/page_allocator_internals_posix.h
@@ -352,8 +352,12 @@ bool TryRecommitSystemPagesInternal(
 
 void DiscardSystemPagesInternal(uintptr_t address, size_t length) {
   void* ptr = reinterpret_cast<void*>(address);
-#if BUILDFLAG(IS_APPLE)
+#if BUILDFLAG(IS_APPLE) || BUILDFLAG(IS_BSD)
+#if BUILDFLAG(IS_BSD)
+  int ret = madvise(ptr, length, MADV_FREE);
+#else
   int ret = madvise(ptr, length, MADV_FREE_REUSABLE);
+#endif
   if (ret) {
     // MADV_FREE_REUSABLE sometimes fails, so fall back to MADV_DONTNEED.
     ret = madvise(ptr, length, MADV_DONTNEED);
