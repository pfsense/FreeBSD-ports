--- third_party/skia/src/opts/SkRasterPipeline_opts.h.orig	2019-09-10 11:16:50 UTC
+++ third_party/skia/src/opts/SkRasterPipeline_opts.h
@@ -729,7 +729,7 @@ SI F approx_powf(F x, F y) {
 }
 
 SI F from_half(U16 h) {
-#if defined(SK_CPU_ARM64) && !defined(SK_BUILD_FOR_GOOGLE3)  // Temporary workaround for some Google3 builds.
+#if defined(JUMPER_IS_NEON) && defined(SK_CPU_ARM64) && !defined(SK_BUILD_FOR_GOOGLE3)  // Temporary workaround for some Google3 builds.
     return vcvt_f32_f16(h);
 
 #elif defined(JUMPER_IS_HSW) || defined(JUMPER_IS_AVX512)
@@ -749,7 +749,7 @@ SI F from_half(U16 h) {
 }
 
 SI U16 to_half(F f) {
-#if defined(SK_CPU_ARM64) && !defined(SK_BUILD_FOR_GOOGLE3)  // Temporary workaround for some Google3 builds.
+#if defined(JUMPER_IS_NEON) && defined(SK_CPU_ARM64) && !defined(SK_BUILD_FOR_GOOGLE3)  // Temporary workaround for some Google3 builds.
     return vcvt_f16_f32(f);
 
 #elif defined(JUMPER_IS_HSW) || defined(JUMPER_IS_AVX512)
