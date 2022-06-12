--- third_party/blink/renderer/platform/wtf/math_extras.h.orig	2022-05-11 07:16:57 UTC
+++ third_party/blink/renderer/platform/wtf/math_extras.h
@@ -127,6 +127,10 @@ constexpr float Grad2turn(float g) {
   return g * (1.0f / 400.0f);
 }
 
+#if defined(OS_FREEBSD)
+#pragma clang diagnostic push
+#pragma clang diagnostic ignored "-Winvalid-constexpr"
+#endif
 constexpr double RoundHalfTowardsPositiveInfinity(double value) {
   return std::floor(value + 0.5);
 }
@@ -134,6 +138,9 @@ constexpr double RoundHalfTowardsPositiveInfinity(doub
 constexpr float RoundHalfTowardsPositiveInfinity(float value) {
   return std::floor(value + 0.5f);
 }
+#if defined(OS_FREEBSD)
+#pragma clang diagnostic pop
+#endif
 
 // ClampTo() is implemented by templated helper classes (to allow for partial
 // template specialization) as well as several helper functions.
