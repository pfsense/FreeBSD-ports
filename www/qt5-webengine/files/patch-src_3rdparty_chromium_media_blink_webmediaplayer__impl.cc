--- src/3rdparty/chromium/media/blink/webmediaplayer_impl.cc.orig	2020-11-07 01:22:36 UTC
+++ src/3rdparty/chromium/media/blink/webmediaplayer_impl.cc
@@ -280,7 +280,11 @@ void CreateAllocation(base::trace_event::ProcessMemory
 
   auto* std_allocator = base::trace_event::MemoryDumpManager::GetInstance()
                             ->system_allocator_pool_name();
-  pmd->AddSuballocation(dump->guid(), std_allocator);
+  if (std_allocator == nullptr) {
+    pmd->AddSuballocation(dump->guid(), std::string());
+  } else {
+    pmd->AddSuballocation(dump->guid(), std_allocator);
+  }
 }
 
 }  // namespace
