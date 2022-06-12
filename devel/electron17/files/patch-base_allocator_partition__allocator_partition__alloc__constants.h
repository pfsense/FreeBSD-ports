--- base/allocator/partition_allocator/partition_alloc_constants.h.orig	2022-05-11 07:16:46 UTC
+++ base/allocator/partition_allocator/partition_alloc_constants.h
@@ -371,7 +371,7 @@ constexpr size_t kBitsPerSizeT = sizeof(void*) * CHAR_
 // PartitionPurgeDecommitEmptySlotSpans flag will eagerly decommit all entries
 // in the ring buffer, so with periodic purge enabled, this typically happens
 // every few seconds.
-#if defined(OS_LINUX) || defined(OS_APPLE)
+#if defined(OS_LINUX) || defined(OS_APPLE) || defined(OS_BSD)
 // Set to a higher value on Linux and macOS, to assess impact on performance
 // bots. This roughly halves the number of syscalls done during a speedometer
 // 2.0 run on these platforms.
