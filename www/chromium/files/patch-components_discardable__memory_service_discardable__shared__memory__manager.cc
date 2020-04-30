--- components/discardable_memory/service/discardable_shared_memory_manager.cc.orig	2020-03-16 18:40:30 UTC
+++ components/discardable_memory/service/discardable_shared_memory_manager.cc
@@ -33,7 +33,7 @@
 #include "components/discardable_memory/common/discardable_shared_memory_heap.h"
 #include "mojo/public/cpp/bindings/self_owned_receiver.h"
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 #include "base/files/file_path.h"
 #include "base/files/file_util.h"
 #include "base/metrics/histogram_macros.h"
@@ -182,7 +182,7 @@ int64_t GetDefaultMemoryLimit() {
     max_default_memory_limit /= 8;
 #endif
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   base::FilePath shmem_dir;
   if (base::GetShmemTempDir(false, &shmem_dir)) {
     int64_t shmem_dir_amount_of_free_space =
