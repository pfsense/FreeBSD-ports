--- src/util/mpz.cpp.orig	2019-11-19 20:58:44 UTC
+++ src/util/mpz.cpp
@@ -72,6 +72,8 @@ inline uint64_t _trailing_zeros64(uint64_t x) {
 
 #if defined(_WINDOWS) && !defined(_M_ARM) && !defined(_M_ARM64)
 // _trailing_zeros32 already defined using intrinsics
+#elif defined(__GNUC__)
+// _trailing_zeros32 already defined using intrinsics
 #else
 inline uint32_t _trailing_zeros32(uint32_t x) {
     uint32_t r = 0;
