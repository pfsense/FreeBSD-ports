--- krep.c.orig	2026-03-18 19:36:12 UTC
+++ krep.c
@@ -4503,6 +4503,27 @@ uint64_t memchr_short_search(const search_params_t *pa
 }
 
 #ifdef __ARM_NEON
+
+#ifdef __aarch64__
+static inline bool
+is_nonzero(uint8x16_t a)
+{
+	return (vmaxvq_u8(a) != 0);
+}
+#else
+/* no vmaxvq_u8() on AArch32 */
+static inline bool
+is_nonzero(uint8x16_t a)
+{
+	uint8x8_t a8, a4;
+
+	a8 = vmax_u8(vget_low_u8(a), vget_high_u8(a));
+	a4 = vpmax_u8(a8, a8);
+
+	return (vget_lane_u32(a4, 0) != 0);
+}
+#endif /* defined(__aarch64__) */
+
 uint64_t neon_search(const search_params_t *params,
                      const char *text_start,
                      size_t text_len,
@@ -4541,7 +4562,7 @@ uint64_t neon_search(const search_params_t *params,
 
         // Check if any match found
         // vmaxvq_u8 returns the maximum value across the vector. If any byte matched (0xFF), result is 0xFF.
-        if (vmaxvq_u8(cmp) != 0)
+        if (is_nonzero(cmp))
         {
             // Extract mask to find exact positions
             // Since NEON doesn't have a direct movemask, we simulate it or iterate.
