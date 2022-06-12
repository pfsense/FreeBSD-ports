--- plugins/builtin/source/content/pl_builtin_functions.cpp.orig	2022-04-17 23:53:01 UTC
+++ plugins/builtin/source/content/pl_builtin_functions.cpp
@@ -203,7 +203,7 @@ namespace hex::plugin::builtin {
                 const auto signIndex = index >> (sizeof(index) * 8 - 1);
                 const auto absIndex  = (index ^ signIndex) - signIndex;
 #else
-                    const auto absIndex = std::abs(index);
+                    const auto absIndex = (unsigned long)std::abs((long)index);
 #endif
 
                 if (absIndex > string.length())
