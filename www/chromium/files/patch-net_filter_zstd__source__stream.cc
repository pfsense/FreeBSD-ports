--- net/filter/zstd_source_stream.cc.orig	2023-10-11 18:22:24 UTC
+++ net/filter/zstd_source_stream.cc
@@ -6,6 +6,7 @@
 
 #include <algorithm>
 #include <utility>
+#include <unordered_map>
 
 #define ZSTD_STATIC_LINKING_ONLY
 
