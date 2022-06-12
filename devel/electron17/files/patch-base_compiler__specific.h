--- base/compiler_specific.h.orig	2022-05-11 07:16:46 UTC
+++ base/compiler_specific.h
@@ -366,7 +366,7 @@ inline constexpr bool AnalyzerAssumeTrue(bool arg) {
 #endif  // defined(__clang_analyzer__)
 
 // Use nomerge attribute to disable optimization of merging multiple same calls.
-#if defined(__clang__) && __has_attribute(nomerge)
+#if defined(__clang__) && __has_attribute(nomerge) && !defined(OS_FREEBSD)
 #define NOMERGE [[clang::nomerge]]
 #else
 #define NOMERGE
