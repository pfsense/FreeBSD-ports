--- base/profiler/stack_sampling_profiler_test_util.cc.orig	2021-04-14 18:40:48 UTC
+++ base/profiler/stack_sampling_profiler_test_util.cc
@@ -32,7 +32,7 @@
 // Fortunately, it provides _alloca, which functions identically.
 #include <malloc.h>
 #define alloca _alloca
-#else
+#elif !defined(OS_BSD)
 #include <alloca.h>
 #endif
 
