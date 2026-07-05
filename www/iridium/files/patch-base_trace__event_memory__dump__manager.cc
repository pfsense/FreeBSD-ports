--- base/trace_event/memory_dump_manager.cc.orig	2026-03-24 16:59:08 UTC
+++ base/trace_event/memory_dump_manager.cc
@@ -95,7 +95,7 @@ const char* const MemoryDumpManager::kSystemAllocatorP
 #if defined(MALLOC_MEMORY_TRACING_SUPPORTED)
     MallocDumpProvider::kAllocatedObjects;
 #else
-    nullptr;
+    "";
 #endif
 
 // static
