--- external/FFmpeg/libswscale/ppc/swscale_vsx.c.orig       2019-08-11 20:06:32 UTC
+++ external/FFmpeg/libswscale/ppc/swscale_vsx.c
@@ -103,9 +103,9 @@ static void yuv2plane1_8_vsx(const int16_t *src, uint8_t *dest, int dstW,
     const int dst_u = -(uintptr_t)dest & 15;
     int i, j;
     LOCAL_ALIGNED(16, int16_t, val, [16]);
-    const vector uint16_t shifts = (vector uint16_t) {7, 7, 7, 7, 7, 7, 7, 7};
-    vector int16_t vi, vileft, ditherleft, ditherright;
-    vector uint8_t vd;
+    const vec_u16 shifts = (vec_u16) {7, 7, 7, 7, 7, 7, 7, 7};
+    vec_s16 vi, vileft, ditherleft, ditherright;
+    vec_u8 vd;
 
     for (j = 0; j < 16; j++) {
         val[j] = dither[(dst_u + offset + j) & 7];
@@ -161,11 +161,11 @@ static void yuv2plane1_nbps_vsx(const int16_t *src, uint16_t *dest, int dstW,
     const int shift = 15 - output_bits;
     const int add = (1 << (shift - 1));
     const int clip = (1 << output_bits) - 1;
-    const vector uint16_t vadd = (vector uint16_t) {add, add, add, add, add, add, add, add};
-    const vector uint16_t vswap = (vector uint16_t) vec_splat_u16(big_endian ? 8 : 0);
-    const vector uint16_t vshift = (vector uint16_t) vec_splat_u16(shift);
-    const vector uint16_t vlargest = (vector uint16_t) {clip, clip, clip, clip, clip, clip, clip, clip};
-    vector uint16_t v;
+    const vec_u16 vadd = (vec_u16) {add, add, add, add, add, add, add, add};
+    const vec_u16 vswap = (vec_u16) vec_splat_u16(big_endian ? 8 : 0);
+    const vec_u16 vshift = (vec_u16) vec_splat_u16(shift);
+    const vec_u16 vlargest = (vec_u16) {clip, clip, clip, clip, clip, clip, clip, clip};
+    vec_u16 v;
     int i;
 
     yuv2plane1_nbps_u(src, dest, dst_u, big_endian, output_bits, 0);
@@ -209,20 +209,20 @@ static void yuv2planeX_nbps_vsx(const int16_t *filter, int filterSize,
     const int add = (1 << (shift - 1));
     const int clip = (1 << output_bits) - 1;
     const uint16_t swap = big_endian ? 8 : 0;
-    const vector uint32_t vadd = (vector uint32_t) {add, add, add, add};
-    const vector uint32_t vshift = (vector uint32_t) {shift, shift, shift, shift};
-    const vector uint16_t vswap = (vector uint16_t) {swap, swap, swap, swap, swap, swap, swap, swap};
-    const vector uint16_t vlargest = (vector uint16_t) {clip, clip, clip, clip, clip, clip, clip, clip};
-    const vector int16_t vzero = vec_splat_s16(0);
-    const vector uint8_t vperm = (vector uint8_t) {0, 1, 8, 9, 2, 3, 10, 11, 4, 5, 12, 13, 6, 7, 14, 15};
-    vector int16_t vfilter[MAX_FILTER_SIZE], vin;
-    vector uint16_t v;
-    vector uint32_t vleft, vright, vtmp;
+    const vec_u32 vadd = (vec_u32) {add, add, add, add};
+    const vec_u32 vshift = (vec_u32) {shift, shift, shift, shift};
+    const vec_u16 vswap = (vec_u16) {swap, swap, swap, swap, swap, swap, swap, swap};
+    const vec_u16 vlargest = (vec_u16) {clip, clip, clip, clip, clip, clip, clip, clip};
+    const vec_s16 vzero = vec_splat_s16(0);
+    const vec_u8 vperm = (vec_u8) {0, 1, 8, 9, 2, 3, 10, 11, 4, 5, 12, 13, 6, 7, 14, 15};
+    vec_s16 vfilter[MAX_FILTER_SIZE], vin;
+    vec_u16 v;
+    vec_u32 vleft, vright, vtmp;
     int i, j;
 
     for (i = 0; i < filterSize; i++) {
-        vfilter[i] = (vector int16_t) {filter[i], filter[i], filter[i], filter[i],
-                                       filter[i], filter[i], filter[i], filter[i]};
+        vfilter[i] = (vec_s16) {filter[i], filter[i], filter[i], filter[i],
+                                filter[i], filter[i], filter[i], filter[i]};
     }
 
     yuv2planeX_nbps_u(filter, filterSize, src, dest, dst_u, big_endian, output_bits, 0);
@@ -232,16 +232,16 @@ static void yuv2planeX_nbps_vsx(const int16_t *filter, int filterSize,
 
         for (j = 0; j < filterSize; j++) {
             vin = vec_vsx_ld(0, &src[j][i]);
-            vtmp = (vector uint32_t) vec_mule(vin, vfilter[j]);
+            vtmp = (vec_u32) vec_mule(vin, vfilter[j]);
             vleft = vec_add(vleft, vtmp);
-            vtmp = (vector uint32_t) vec_mulo(vin, vfilter[j]);
+            vtmp = (vec_u32) vec_mulo(vin, vfilter[j]);
             vright = vec_add(vright, vtmp);
         }
 
         vleft = vec_sra(vleft, vshift);
         vright = vec_sra(vright, vshift);
         v = vec_packsu(vleft, vright);
-        v = (vector uint16_t) vec_max((vector int16_t) v, vzero);
+        v = (vec_u16) vec_max((vec_s16) v, vzero);
         v = vec_min(v, vlargest);
         v = vec_rl(v, vswap);
         v = vec_perm(v, v, vperm);
@@ -279,11 +279,11 @@ static void yuv2plane1_16_vsx(const int32_t *src, uint16_t *dest, int dstW,
     const int dst_u = -(uintptr_t)dest & 7;
     const int shift = 3;
     const int add = (1 << (shift - 1));
-    const vector uint32_t vadd = (vector uint32_t) {add, add, add, add};
-    const vector uint16_t vswap = (vector uint16_t) vec_splat_u16(big_endian ? 8 : 0);
-    const vector uint32_t vshift = (vector uint32_t) vec_splat_u32(shift);
-    vector uint32_t v, v2;
-    vector uint16_t vd;
+    const vec_u32 vadd = (vec_u32) {add, add, add, add};
+    const vec_u16 vswap = (vec_u16) vec_splat_u16(big_endian ? 8 : 0);
+    const vec_u32 vshift = (vec_u32) vec_splat_u32(shift);
+    vec_u32 v, v2;
+    vec_u16 vd;
     int i;
 
     yuv2plane1_16_u(src, dest, dst_u, big_endian, output_bits, 0);
@@ -341,18 +341,18 @@ static void yuv2planeX_16_vsx(const int16_t *filter, int filterSize,
     const int bias = 0x8000;
     const int add = (1 << (shift - 1)) - 0x40000000;
     const uint16_t swap = big_endian ? 8 : 0;
-    const vector uint32_t vadd = (vector uint32_t) {add, add, add, add};
-    const vector uint32_t vshift = (vector uint32_t) {shift, shift, shift, shift};
-    const vector uint16_t vswap = (vector uint16_t) {swap, swap, swap, swap, swap, swap, swap, swap};
-    const vector uint16_t vbias = (vector uint16_t) {bias, bias, bias, bias, bias, bias, bias, bias};
-    vector int32_t vfilter[MAX_FILTER_SIZE];
-    vector uint16_t v;
-    vector uint32_t vleft, vright, vtmp;
-    vector int32_t vin32l, vin32r;
+    const vec_u32 vadd = (vec_u32) {add, add, add, add};
+    const vec_u32 vshift = (vec_u32) {shift, shift, shift, shift};
+    const vec_u16 vswap = (vec_u16) {swap, swap, swap, swap, swap, swap, swap, swap};
+    const vec_u16 vbias = (vec_u16) {bias, bias, bias, bias, bias, bias, bias, bias};
+    vec_s32 vfilter[MAX_FILTER_SIZE];
+    vec_u16 v;
+    vec_u32 vleft, vright, vtmp;
+    vec_s32 vin32l, vin32r;
     int i, j;
 
     for (i = 0; i < filterSize; i++) {
-        vfilter[i] = (vector int32_t) {filter[i], filter[i], filter[i], filter[i]};
+        vfilter[i] = (vec_s32) {filter[i], filter[i], filter[i], filter[i]};
     }
 
     yuv2planeX_16_u(filter, filterSize, src, dest, dst_u, big_endian, output_bits, 0);
@@ -364,15 +364,15 @@ static void yuv2planeX_16_vsx(const int16_t *filter, int filterSize,
             vin32l = vec_vsx_ld(0, &src[j][i]);
             vin32r = vec_vsx_ld(0, &src[j][i + 4]);
 
-            vtmp = (vector uint32_t) vec_mul(vin32l, vfilter[j]);
+            vtmp = (vec_u32) vec_mul(vin32l, vfilter[j]);
             vleft = vec_add(vleft, vtmp);
-            vtmp = (vector uint32_t) vec_mul(vin32r, vfilter[j]);
+            vtmp = (vec_u32) vec_mul(vin32r, vfilter[j]);
             vright = vec_add(vright, vtmp);
         }
 
         vleft = vec_sra(vleft, vshift);
         vright = vec_sra(vright, vshift);
-        v = (vector uint16_t) vec_packs((vector int32_t) vleft, (vector int32_t) vright);
+        v = (vec_u16) vec_packs((vec_s32) vleft, (vec_s32) vright);
         v = vec_add(v, vbias);
         v = vec_rl(v, vswap);
         vec_st(v, 0, &dest[i]);
@@ -478,9 +478,9 @@ yuv2NBPSX(16, LE, 0, 16, int32_t)
             out0 = vec_mergeh(bd, gd); \
             out1 = vec_mergeh(rd, ad); \
 \
-            tmp8 = (vector uint8_t) vec_mergeh((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergeh((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 0, dest); \
-            tmp8 = (vector uint8_t) vec_mergel((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergel((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 16, dest); \
 \
             dest += 32; \
@@ -489,9 +489,9 @@ yuv2NBPSX(16, LE, 0, 16, int32_t)
             out0 = vec_mergeh(rd, gd); \
             out1 = vec_mergeh(bd, ad); \
 \
-            tmp8 = (vector uint8_t) vec_mergeh((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergeh((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 0, dest); \
-            tmp8 = (vector uint8_t) vec_mergel((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergel((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 16, dest); \
 \
             dest += 32; \
@@ -500,9 +500,9 @@ yuv2NBPSX(16, LE, 0, 16, int32_t)
             out0 = vec_mergeh(ad, rd); \
             out1 = vec_mergeh(gd, bd); \
 \
-            tmp8 = (vector uint8_t) vec_mergeh((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergeh((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 0, dest); \
-            tmp8 = (vector uint8_t) vec_mergel((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergel((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 16, dest); \
 \
             dest += 32; \
@@ -511,9 +511,9 @@ yuv2NBPSX(16, LE, 0, 16, int32_t)
             out0 = vec_mergeh(ad, bd); \
             out1 = vec_mergeh(gd, rd); \
 \
-            tmp8 = (vector uint8_t) vec_mergeh((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergeh((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 0, dest); \
-            tmp8 = (vector uint8_t) vec_mergel((vector uint16_t) out0, (vector uint16_t) out1); \
+            tmp8 = (vec_u8) vec_mergel((vec_u16) out0, (vec_u16) out1); \
             vec_vsx_st(tmp8, 16, dest); \
 \
             dest += 32; \
@@ -528,48 +528,48 @@ yuv2rgb_full_X_vsx_template(SwsContext *c, const int16_t *lumFilter,
                           const int16_t **alpSrc, uint8_t *dest,
                           int dstW, int y, enum AVPixelFormat target, int hasAlpha)
 {
-    vector int16_t vv;
-    vector int32_t vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32;
-    vector int32_t R_l, R_r, G_l, G_r, B_l, B_r;
-    vector int32_t tmp, tmp2, tmp3, tmp4;
-    vector uint16_t rd16, gd16, bd16;
-    vector uint8_t rd, bd, gd, ad, out0, out1, tmp8;
-    vector int16_t vlumFilter[MAX_FILTER_SIZE], vchrFilter[MAX_FILTER_SIZE];
-    const vector int32_t ystart = vec_splats(1 << 9);
-    const vector int32_t uvstart = vec_splats((1 << 9) - (128 << 19));
-    const vector uint16_t zero16 = vec_splat_u16(0);
-    const vector int32_t y_offset = vec_splats(c->yuv2rgb_y_offset);
-    const vector int32_t y_coeff = vec_splats(c->yuv2rgb_y_coeff);
-    const vector int32_t y_add = vec_splats(1 << 21);
-    const vector int32_t v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
-    const vector int32_t v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
-    const vector int32_t u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
-    const vector int32_t u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
-    const vector int32_t rgbclip = vec_splats(1 << 30);
-    const vector int32_t zero32 = vec_splat_s32(0);
-    const vector uint32_t shift22 = vec_splats(22U);
-    const vector uint32_t shift10 = vec_splat_u32(10);
+    vec_s16 vv;
+    vec_s32 vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32;
+    vec_s32 R_l, R_r, G_l, G_r, B_l, B_r;
+    vec_s32 tmp, tmp2, tmp3, tmp4;
+    vec_u16 rd16, gd16, bd16;
+    vec_u8 rd, bd, gd, ad, out0, out1, tmp8;
+    vec_s16 vlumFilter[MAX_FILTER_SIZE], vchrFilter[MAX_FILTER_SIZE];
+    const vec_s32 ystart = vec_splats(1 << 9);
+    const vec_s32 uvstart = vec_splats((1 << 9) - (128 << 19));
+    const vec_u16 zero16 = vec_splat_u16(0);
+    const vec_s32 y_offset = vec_splats(c->yuv2rgb_y_offset);
+    const vec_s32 y_coeff = vec_splats(c->yuv2rgb_y_coeff);
+    const vec_s32 y_add = vec_splats(1 << 21);
+    const vec_s32 v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
+    const vec_s32 v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
+    const vec_s32 u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
+    const vec_s32 u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
+    const vec_s32 rgbclip = vec_splats(1 << 30);
+    const vec_s32 zero32 = vec_splat_s32(0);
+    const vec_u32 shift22 = vec_splats(22U);
+    const vec_u32 shift10 = vec_splat_u32(10);
     int i, j;
 
     // Various permutations
-    const vector uint8_t perm3rg0 = (vector uint8_t) {0x0, 0x10, 0,
-                                                      0x1, 0x11, 0,
-                                                      0x2, 0x12, 0,
-                                                      0x3, 0x13, 0,
-                                                      0x4, 0x14, 0,
-                                                      0x5 };
-    const vector uint8_t perm3rg1 = (vector uint8_t) {     0x15, 0,
-                                                      0x6, 0x16, 0,
-                                                      0x7, 0x17, 0 };
-    const vector uint8_t perm3tb0 = (vector uint8_t) {0x0, 0x1, 0x10,
-                                                      0x3, 0x4, 0x11,
-                                                      0x6, 0x7, 0x12,
-                                                      0x9, 0xa, 0x13,
-                                                      0xc, 0xd, 0x14,
-                                                      0xf };
-    const vector uint8_t perm3tb1 = (vector uint8_t) {     0x0, 0x15,
-                                                      0x2, 0x3, 0x16,
-                                                      0x5, 0x6, 0x17 };
+    const vec_u8 perm3rg0 = (vec_u8) {0x0, 0x10, 0,
+                                      0x1, 0x11, 0,
+                                      0x2, 0x12, 0,
+                                      0x3, 0x13, 0,
+                                      0x4, 0x14, 0,
+                                      0x5 };
+    const vec_u8 perm3rg1 = (vec_u8) {     0x15, 0,
+                                      0x6, 0x16, 0,
+                                      0x7, 0x17, 0 };
+    const vec_u8 perm3tb0 = (vec_u8) {0x0, 0x1, 0x10,
+                                      0x3, 0x4, 0x11,
+                                      0x6, 0x7, 0x12,
+                                      0x9, 0xa, 0x13,
+                                      0xc, 0xd, 0x14,
+                                      0xf };
+    const vec_u8 perm3tb1 = (vec_u8) {     0x0, 0x15,
+                                      0x2, 0x3, 0x16,
+                                      0x5, 0x6, 0x17 };
 
     ad = vec_splats((uint8_t) 255);
 
@@ -685,52 +685,52 @@ yuv2rgb_full_2_vsx_template(SwsContext *c, const int16_t *buf[2],
                   *abuf1 = hasAlpha ? abuf[1] : NULL;
     const int16_t  yalpha1 = 4096 - yalpha;
     const int16_t uvalpha1 = 4096 - uvalpha;
-    vector int16_t vy, vu, vv, A = vec_splat_s16(0);
-    vector int32_t vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32;
-    vector int32_t R_l, R_r, G_l, G_r, B_l, B_r;
-    vector int32_t tmp, tmp2, tmp3, tmp4, tmp5, tmp6;
-    vector uint16_t rd16, gd16, bd16;
-    vector uint8_t rd, bd, gd, ad, out0, out1, tmp8;
-    const vector int16_t vyalpha1 = vec_splats(yalpha1);
-    const vector int16_t vuvalpha1 = vec_splats(uvalpha1);
-    const vector int16_t vyalpha = vec_splats((int16_t) yalpha);
-    const vector int16_t vuvalpha = vec_splats((int16_t) uvalpha);
-    const vector uint16_t zero16 = vec_splat_u16(0);
-    const vector int32_t y_offset = vec_splats(c->yuv2rgb_y_offset);
-    const vector int32_t y_coeff = vec_splats(c->yuv2rgb_y_coeff);
-    const vector int32_t y_add = vec_splats(1 << 21);
-    const vector int32_t v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
-    const vector int32_t v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
-    const vector int32_t u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
-    const vector int32_t u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
-    const vector int32_t rgbclip = vec_splats(1 << 30);
-    const vector int32_t zero32 = vec_splat_s32(0);
-    const vector uint32_t shift19 = vec_splats(19U);
-    const vector uint32_t shift22 = vec_splats(22U);
-    const vector uint32_t shift10 = vec_splat_u32(10);
-    const vector int32_t dec128 = vec_splats(128 << 19);
-    const vector int32_t add18 = vec_splats(1 << 18);
+    vec_s16 vy, vu, vv, A = vec_splat_s16(0);
+    vec_s32 vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32;
+    vec_s32 R_l, R_r, G_l, G_r, B_l, B_r;
+    vec_s32 tmp, tmp2, tmp3, tmp4, tmp5, tmp6;
+    vec_u16 rd16, gd16, bd16;
+    vec_u8 rd, bd, gd, ad, out0, out1, tmp8;
+    const vec_s16 vyalpha1 = vec_splats(yalpha1);
+    const vec_s16 vuvalpha1 = vec_splats(uvalpha1);
+    const vec_s16 vyalpha = vec_splats((int16_t) yalpha);
+    const vec_s16 vuvalpha = vec_splats((int16_t) uvalpha);
+    const vec_u16 zero16 = vec_splat_u16(0);
+    const vec_s32 y_offset = vec_splats(c->yuv2rgb_y_offset);
+    const vec_s32 y_coeff = vec_splats(c->yuv2rgb_y_coeff);
+    const vec_s32 y_add = vec_splats(1 << 21);
+    const vec_s32 v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
+    const vec_s32 v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
+    const vec_s32 u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
+    const vec_s32 u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
+    const vec_s32 rgbclip = vec_splats(1 << 30);
+    const vec_s32 zero32 = vec_splat_s32(0);
+    const vec_u32 shift19 = vec_splats(19U);
+    const vec_u32 shift22 = vec_splats(22U);
+    const vec_u32 shift10 = vec_splat_u32(10);
+    const vec_s32 dec128 = vec_splats(128 << 19);
+    const vec_s32 add18 = vec_splats(1 << 18);
     int i;
 
     // Various permutations
-    const vector uint8_t perm3rg0 = (vector uint8_t) {0x0, 0x10, 0,
-                                                      0x1, 0x11, 0,
-                                                      0x2, 0x12, 0,
-                                                      0x3, 0x13, 0,
-                                                      0x4, 0x14, 0,
-                                                      0x5 };
-    const vector uint8_t perm3rg1 = (vector uint8_t) {     0x15, 0,
-                                                      0x6, 0x16, 0,
-                                                      0x7, 0x17, 0 };
-    const vector uint8_t perm3tb0 = (vector uint8_t) {0x0, 0x1, 0x10,
-                                                      0x3, 0x4, 0x11,
-                                                      0x6, 0x7, 0x12,
-                                                      0x9, 0xa, 0x13,
-                                                      0xc, 0xd, 0x14,
-                                                      0xf };
-    const vector uint8_t perm3tb1 = (vector uint8_t) {     0x0, 0x15,
-                                                      0x2, 0x3, 0x16,
-                                                      0x5, 0x6, 0x17 };
+    const vec_u8 perm3rg0 = (vec_u8) {0x0, 0x10, 0,
+                                      0x1, 0x11, 0,
+                                      0x2, 0x12, 0,
+                                      0x3, 0x13, 0,
+                                      0x4, 0x14, 0,
+                                      0x5 };
+    const vec_u8 perm3rg1 = (vec_u8) {     0x15, 0,
+                                      0x6, 0x16, 0,
+                                      0x7, 0x17, 0 };
+    const vec_u8 perm3tb0 = (vec_u8) {0x0, 0x1, 0x10,
+                                      0x3, 0x4, 0x11,
+                                      0x6, 0x7, 0x12,
+                                      0x9, 0xa, 0x13,
+                                      0xc, 0xd, 0x14,
+                                      0xf };
+    const vec_u8 perm3tb1 = (vec_u8) {     0x0, 0x15,
+                                      0x2, 0x3, 0x16,
+                                      0x5, 0x6, 0x17 };
 
     av_assert2(yalpha  <= 4096U);
     av_assert2(uvalpha <= 4096U);
@@ -759,7 +759,7 @@ yuv2rgb_full_2_vsx_template(SwsContext *c, const int16_t *buf[2],
             tmp3 = vec_sra(tmp3, shift19);
             tmp4 = vec_sra(tmp4, shift19);
             A = vec_packs(tmp3, tmp4);
-            ad = vec_packsu(A, (vector int16_t) zero16);
+            ad = vec_packsu(A, (vec_s16) zero16);
         } else {
             ad = vec_splats((uint8_t) 255);
         }
@@ -807,60 +807,60 @@ yuv2rgb_2_vsx_template(SwsContext *c, const int16_t *buf[2],
                   *abuf1 = hasAlpha ? abuf[1] : NULL;
     const int16_t  yalpha1 = 4096 - yalpha;
     const int16_t uvalpha1 = 4096 - uvalpha;
-    vector int16_t vy, vu, vv, A = vec_splat_s16(0);
-    vector int32_t vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32;
-    vector int32_t R_l, R_r, G_l, G_r, B_l, B_r, vud32_l, vud32_r, vvd32_l, vvd32_r;
-    vector int32_t tmp, tmp2, tmp3, tmp4, tmp5, tmp6;
-    vector uint16_t rd16, gd16, bd16;
-    vector uint8_t rd, bd, gd, ad, out0, out1, tmp8;
-    const vector int16_t vyalpha1 = vec_splats(yalpha1);
-    const vector int16_t vuvalpha1 = vec_splats(uvalpha1);
-    const vector int16_t vyalpha = vec_splats((int16_t) yalpha);
-    const vector int16_t vuvalpha = vec_splats((int16_t) uvalpha);
-    const vector uint16_t zero16 = vec_splat_u16(0);
-    const vector int32_t y_offset = vec_splats(c->yuv2rgb_y_offset);
-    const vector int32_t y_coeff = vec_splats(c->yuv2rgb_y_coeff);
-    const vector int32_t y_add = vec_splats(1 << 21);
-    const vector int32_t v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
-    const vector int32_t v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
-    const vector int32_t u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
-    const vector int32_t u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
-    const vector int32_t rgbclip = vec_splats(1 << 30);
-    const vector int32_t zero32 = vec_splat_s32(0);
-    const vector uint32_t shift19 = vec_splats(19U);
-    const vector uint32_t shift22 = vec_splats(22U);
-    const vector uint32_t shift10 = vec_splat_u32(10);
-    const vector int32_t dec128 = vec_splats(128 << 19);
-    const vector int32_t add18 = vec_splats(1 << 18);
+    vec_s16 vy, vu, vv, A = vec_splat_s16(0);
+    vec_s32 vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32;
+    vec_s32 R_l, R_r, G_l, G_r, B_l, B_r, vud32_l, vud32_r, vvd32_l, vvd32_r;
+    vec_s32 tmp, tmp2, tmp3, tmp4, tmp5, tmp6;
+    vec_u16 rd16, gd16, bd16;
+    vec_u8 rd, bd, gd, ad, out0, out1, tmp8;
+    const vec_s16 vyalpha1 = vec_splats(yalpha1);
+    const vec_s16 vuvalpha1 = vec_splats(uvalpha1);
+    const vec_s16 vyalpha = vec_splats((int16_t) yalpha);
+    const vec_s16 vuvalpha = vec_splats((int16_t) uvalpha);
+    const vec_u16 zero16 = vec_splat_u16(0);
+    const vec_s32 y_offset = vec_splats(c->yuv2rgb_y_offset);
+    const vec_s32 y_coeff = vec_splats(c->yuv2rgb_y_coeff);
+    const vec_s32 y_add = vec_splats(1 << 21);
+    const vec_s32 v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
+    const vec_s32 v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
+    const vec_s32 u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
+    const vec_s32 u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
+    const vec_s32 rgbclip = vec_splats(1 << 30);
+    const vec_s32 zero32 = vec_splat_s32(0);
+    const vec_u32 shift19 = vec_splats(19U);
+    const vec_u32 shift22 = vec_splats(22U);
+    const vec_u32 shift10 = vec_splat_u32(10);
+    const vec_s32 dec128 = vec_splats(128 << 19);
+    const vec_s32 add18 = vec_splats(1 << 18);
     int i;
 
     // Various permutations
-    const vector uint8_t doubleleft = (vector uint8_t) {0, 1, 2, 3,
-                                                        0, 1, 2, 3,
-                                                        4, 5, 6, 7,
-                                                        4, 5, 6, 7 };
-    const vector uint8_t doubleright = (vector uint8_t) {8, 9, 10, 11,
-                                                        8, 9, 10, 11,
-                                                        12, 13, 14, 15,
-                                                        12, 13, 14, 15 };
-    const vector uint8_t perm3rg0 = (vector uint8_t) {0x0, 0x10, 0,
-                                                      0x1, 0x11, 0,
-                                                      0x2, 0x12, 0,
-                                                      0x3, 0x13, 0,
-                                                      0x4, 0x14, 0,
-                                                      0x5 };
-    const vector uint8_t perm3rg1 = (vector uint8_t) {     0x15, 0,
-                                                      0x6, 0x16, 0,
-                                                      0x7, 0x17, 0 };
-    const vector uint8_t perm3tb0 = (vector uint8_t) {0x0, 0x1, 0x10,
-                                                      0x3, 0x4, 0x11,
-                                                      0x6, 0x7, 0x12,
-                                                      0x9, 0xa, 0x13,
-                                                      0xc, 0xd, 0x14,
-                                                      0xf };
-    const vector uint8_t perm3tb1 = (vector uint8_t) {     0x0, 0x15,
-                                                      0x2, 0x3, 0x16,
-                                                      0x5, 0x6, 0x17 };
+    const vec_u8 doubleleft = (vec_u8) {0, 1, 2, 3,
+                                        0, 1, 2, 3,
+                                        4, 5, 6, 7,
+                                        4, 5, 6, 7 };
+    const vec_u8 doubleright = (vec_u8) {8, 9, 10, 11,
+                                         8, 9, 10, 11,
+                                         12, 13, 14, 15,
+                                         12, 13, 14, 15 };
+    const vec_u8 perm3rg0 = (vec_u8) {0x0, 0x10, 0,
+                                      0x1, 0x11, 0,
+                                      0x2, 0x12, 0,
+                                      0x3, 0x13, 0,
+                                      0x4, 0x14, 0,
+                                      0x5 };
+    const vec_u8 perm3rg1 = (vec_u8) {     0x15, 0,
+                                      0x6, 0x16, 0,
+                                      0x7, 0x17, 0 };
+    const vec_u8 perm3tb0 = (vec_u8) {0x0, 0x1, 0x10,
+                                      0x3, 0x4, 0x11,
+                                      0x6, 0x7, 0x12,
+                                      0x9, 0xa, 0x13,
+                                      0xc, 0xd, 0x14,
+                                      0xf };
+    const vec_u8 perm3tb1 = (vec_u8) {     0x0, 0x15,
+                                      0x2, 0x3, 0x16,
+                                      0x5, 0x6, 0x17 };
 
     av_assert2(yalpha  <= 4096U);
     av_assert2(uvalpha <= 4096U);
@@ -889,7 +889,7 @@ yuv2rgb_2_vsx_template(SwsContext *c, const int16_t *buf[2],
             tmp3 = vec_sra(tmp3, shift19);
             tmp4 = vec_sra(tmp4, shift19);
             A = vec_packs(tmp3, tmp4);
-            ad = vec_packsu(A, (vector int16_t) zero16);
+            ad = vec_packsu(A, (vec_s16) zero16);
         } else {
             ad = vec_splats((uint8_t) 255);
         }
@@ -978,51 +978,51 @@ yuv2rgb_full_1_vsx_template(SwsContext *c, const int16_t *buf0,
 {
     const int16_t *ubuf0 = ubuf[0], *vbuf0 = vbuf[0];
     const int16_t *ubuf1 = ubuf[1], *vbuf1 = vbuf[1];
-    vector int16_t vy, vu, vv, A = vec_splat_s16(0), tmp16;
-    vector int32_t vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32, tmp32_2;
-    vector int32_t R_l, R_r, G_l, G_r, B_l, B_r;
-    vector uint16_t rd16, gd16, bd16;
-    vector uint8_t rd, bd, gd, ad, out0, out1, tmp8;
-    const vector uint16_t zero16 = vec_splat_u16(0);
-    const vector int32_t y_offset = vec_splats(c->yuv2rgb_y_offset);
-    const vector int32_t y_coeff = vec_splats(c->yuv2rgb_y_coeff);
-    const vector int32_t y_add = vec_splats(1 << 21);
-    const vector int32_t v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
-    const vector int32_t v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
-    const vector int32_t u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
-    const vector int32_t u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
-    const vector int32_t rgbclip = vec_splats(1 << 30);
-    const vector int32_t zero32 = vec_splat_s32(0);
-    const vector uint32_t shift2 = vec_splat_u32(2);
-    const vector uint32_t shift22 = vec_splats(22U);
-    const vector uint16_t sub7 = vec_splats((uint16_t) (128 << 7));
-    const vector uint16_t sub8 = vec_splats((uint16_t) (128 << 8));
-    const vector int16_t mul4 = vec_splat_s16(4);
-    const vector int16_t mul8 = vec_splat_s16(8);
-    const vector int16_t add64 = vec_splat_s16(64);
-    const vector uint16_t shift7 = vec_splat_u16(7);
-    const vector int16_t max255 = vec_splat_s16(255);
+    vec_s16 vy, vu, vv, A = vec_splat_s16(0), tmp16;
+    vec_s32 vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32, tmp32_2;
+    vec_s32 R_l, R_r, G_l, G_r, B_l, B_r;
+    vec_u16 rd16, gd16, bd16;
+    vec_u8 rd, bd, gd, ad, out0, out1, tmp8;
+    const vec_u16 zero16 = vec_splat_u16(0);
+    const vec_s32 y_offset = vec_splats(c->yuv2rgb_y_offset);
+    const vec_s32 y_coeff = vec_splats(c->yuv2rgb_y_coeff);
+    const vec_s32 y_add = vec_splats(1 << 21);
+    const vec_s32 v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
+    const vec_s32 v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
+    const vec_s32 u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
+    const vec_s32 u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
+    const vec_s32 rgbclip = vec_splats(1 << 30);
+    const vec_s32 zero32 = vec_splat_s32(0);
+    const vec_u32 shift2 = vec_splat_u32(2);
+    const vec_u32 shift22 = vec_splats(22U);
+    const vec_u16 sub7 = vec_splats((uint16_t) (128 << 7));
+    const vec_u16 sub8 = vec_splats((uint16_t) (128 << 8));
+    const vec_s16 mul4 = vec_splat_s16(4);
+    const vec_s16 mul8 = vec_splat_s16(8);
+    const vec_s16 add64 = vec_splat_s16(64);
+    const vec_u16 shift7 = vec_splat_u16(7);
+    const vec_s16 max255 = vec_splat_s16(255);
     int i;
 
     // Various permutations
-    const vector uint8_t perm3rg0 = (vector uint8_t) {0x0, 0x10, 0,
-                                                      0x1, 0x11, 0,
-                                                      0x2, 0x12, 0,
-                                                      0x3, 0x13, 0,
-                                                      0x4, 0x14, 0,
-                                                      0x5 };
-    const vector uint8_t perm3rg1 = (vector uint8_t) {     0x15, 0,
-                                                      0x6, 0x16, 0,
-                                                      0x7, 0x17, 0 };
-    const vector uint8_t perm3tb0 = (vector uint8_t) {0x0, 0x1, 0x10,
-                                                      0x3, 0x4, 0x11,
-                                                      0x6, 0x7, 0x12,
-                                                      0x9, 0xa, 0x13,
-                                                      0xc, 0xd, 0x14,
-                                                      0xf };
-    const vector uint8_t perm3tb1 = (vector uint8_t) {     0x0, 0x15,
-                                                      0x2, 0x3, 0x16,
-                                                      0x5, 0x6, 0x17 };
+    const vec_u8 perm3rg0 = (vec_u8) {0x0, 0x10, 0,
+                                      0x1, 0x11, 0,
+                                      0x2, 0x12, 0,
+                                      0x3, 0x13, 0,
+                                      0x4, 0x14, 0,
+                                      0x5 };
+    const vec_u8 perm3rg1 = (vec_u8) {     0x15, 0,
+                                      0x6, 0x16, 0,
+                                      0x7, 0x17, 0 };
+    const vec_u8 perm3tb0 = (vec_u8) {0x0, 0x1, 0x10,
+                                      0x3, 0x4, 0x11,
+                                      0x6, 0x7, 0x12,
+                                      0x9, 0xa, 0x13,
+                                      0xc, 0xd, 0x14,
+                                      0xf };
+    const vec_u8 perm3tb1 = (vec_u8) {     0x0, 0x15,
+                                      0x2, 0x3, 0x16,
+                                      0x5, 0x6, 0x17 };
 
     for (i = 0; i < dstW; i += 8) { // The x86 asm also overwrites padding bytes.
         vy = vec_ld(0, &buf0[i]);
@@ -1034,8 +1034,8 @@ yuv2rgb_full_1_vsx_template(SwsContext *c, const int16_t *buf0,
         vu = vec_ld(0, &ubuf0[i]);
         vv = vec_ld(0, &vbuf0[i]);
         if (uvalpha < 2048) {
-            vu = (vector int16_t) vec_sub((vector uint16_t) vu, sub7);
-            vv = (vector int16_t) vec_sub((vector uint16_t) vv, sub7);
+            vu = (vec_s16) vec_sub((vec_u16) vu, sub7);
+            vv = (vec_s16) vec_sub((vec_u16) vv, sub7);
 
             tmp32 = vec_mule(vu, mul4);
             tmp32_2 = vec_mulo(vu, mul4);
@@ -1048,10 +1048,10 @@ yuv2rgb_full_1_vsx_template(SwsContext *c, const int16_t *buf0,
         } else {
             tmp16 = vec_ld(0, &ubuf1[i]);
             vu = vec_add(vu, tmp16);
-            vu = (vector int16_t) vec_sub((vector uint16_t) vu, sub8);
+            vu = (vec_s16) vec_sub((vec_u16) vu, sub8);
             tmp16 = vec_ld(0, &vbuf1[i]);
             vv = vec_add(vv, tmp16);
-            vv = (vector int16_t) vec_sub((vector uint16_t) vv, sub8);
+            vv = (vec_s16) vec_sub((vec_u16) vv, sub8);
 
             vu32_l = vec_mule(vu, mul8);
             vu32_r = vec_mulo(vu, mul8);
@@ -1064,7 +1064,7 @@ yuv2rgb_full_1_vsx_template(SwsContext *c, const int16_t *buf0,
             A = vec_add(A, add64);
             A = vec_sr(A, shift7);
             A = vec_max(A, max255);
-            ad = vec_packsu(A, (vector int16_t) zero16);
+            ad = vec_packsu(A, (vec_s16) zero16);
         } else {
             ad = vec_splats((uint8_t) 255);
         }
@@ -1107,60 +1107,60 @@ yuv2rgb_1_vsx_template(SwsContext *c, const int16_t *buf0,
 {
     const int16_t *ubuf0 = ubuf[0], *vbuf0 = vbuf[0];
     const int16_t *ubuf1 = ubuf[1], *vbuf1 = vbuf[1];
-    vector int16_t vy, vu, vv, A = vec_splat_s16(0), tmp16;
-    vector int32_t vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32, tmp32_2;
-    vector int32_t vud32_l, vud32_r, vvd32_l, vvd32_r;
-    vector int32_t R_l, R_r, G_l, G_r, B_l, B_r;
-    vector uint16_t rd16, gd16, bd16;
-    vector uint8_t rd, bd, gd, ad, out0, out1, tmp8;
-    const vector uint16_t zero16 = vec_splat_u16(0);
-    const vector int32_t y_offset = vec_splats(c->yuv2rgb_y_offset);
-    const vector int32_t y_coeff = vec_splats(c->yuv2rgb_y_coeff);
-    const vector int32_t y_add = vec_splats(1 << 21);
-    const vector int32_t v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
-    const vector int32_t v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
-    const vector int32_t u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
-    const vector int32_t u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
-    const vector int32_t rgbclip = vec_splats(1 << 30);
-    const vector int32_t zero32 = vec_splat_s32(0);
-    const vector uint32_t shift2 = vec_splat_u32(2);
-    const vector uint32_t shift22 = vec_splats(22U);
-    const vector uint16_t sub7 = vec_splats((uint16_t) (128 << 7));
-    const vector uint16_t sub8 = vec_splats((uint16_t) (128 << 8));
-    const vector int16_t mul4 = vec_splat_s16(4);
-    const vector int16_t mul8 = vec_splat_s16(8);
-    const vector int16_t add64 = vec_splat_s16(64);
-    const vector uint16_t shift7 = vec_splat_u16(7);
-    const vector int16_t max255 = vec_splat_s16(255);
+    vec_s16 vy, vu, vv, A = vec_splat_s16(0), tmp16;
+    vec_s32 vy32_l, vy32_r, vu32_l, vu32_r, vv32_l, vv32_r, tmp32, tmp32_2;
+    vec_s32 vud32_l, vud32_r, vvd32_l, vvd32_r;
+    vec_s32 R_l, R_r, G_l, G_r, B_l, B_r;
+    vec_u16 rd16, gd16, bd16;
+    vec_u8 rd, bd, gd, ad, out0, out1, tmp8;
+    const vec_u16 zero16 = vec_splat_u16(0);
+    const vec_s32 y_offset = vec_splats(c->yuv2rgb_y_offset);
+    const vec_s32 y_coeff = vec_splats(c->yuv2rgb_y_coeff);
+    const vec_s32 y_add = vec_splats(1 << 21);
+    const vec_s32 v2r_coeff = vec_splats(c->yuv2rgb_v2r_coeff);
+    const vec_s32 v2g_coeff = vec_splats(c->yuv2rgb_v2g_coeff);
+    const vec_s32 u2g_coeff = vec_splats(c->yuv2rgb_u2g_coeff);
+    const vec_s32 u2b_coeff = vec_splats(c->yuv2rgb_u2b_coeff);
+    const vec_s32 rgbclip = vec_splats(1 << 30);
+    const vec_s32 zero32 = vec_splat_s32(0);
+    const vec_u32 shift2 = vec_splat_u32(2);
+    const vec_u32 shift22 = vec_splats(22U);
+    const vec_u16 sub7 = vec_splats((uint16_t) (128 << 7));
+    const vec_u16 sub8 = vec_splats((uint16_t) (128 << 8));
+    const vec_s16 mul4 = vec_splat_s16(4);
+    const vec_s16 mul8 = vec_splat_s16(8);
+    const vec_s16 add64 = vec_splat_s16(64);
+    const vec_u16 shift7 = vec_splat_u16(7);
+    const vec_s16 max255 = vec_splat_s16(255);
     int i;
 
     // Various permutations
-    const vector uint8_t doubleleft = (vector uint8_t) {0, 1, 2, 3,
-                                                        0, 1, 2, 3,
-                                                        4, 5, 6, 7,
-                                                        4, 5, 6, 7 };
-    const vector uint8_t doubleright = (vector uint8_t) {8, 9, 10, 11,
-                                                        8, 9, 10, 11,
-                                                        12, 13, 14, 15,
-                                                        12, 13, 14, 15 };
-    const vector uint8_t perm3rg0 = (vector uint8_t) {0x0, 0x10, 0,
-                                                      0x1, 0x11, 0,
-                                                      0x2, 0x12, 0,
-                                                      0x3, 0x13, 0,
-                                                      0x4, 0x14, 0,
-                                                      0x5 };
-    const vector uint8_t perm3rg1 = (vector uint8_t) {     0x15, 0,
-                                                      0x6, 0x16, 0,
-                                                      0x7, 0x17, 0 };
-    const vector uint8_t perm3tb0 = (vector uint8_t) {0x0, 0x1, 0x10,
-                                                      0x3, 0x4, 0x11,
-                                                      0x6, 0x7, 0x12,
-                                                      0x9, 0xa, 0x13,
-                                                      0xc, 0xd, 0x14,
-                                                      0xf };
-    const vector uint8_t perm3tb1 = (vector uint8_t) {     0x0, 0x15,
-                                                      0x2, 0x3, 0x16,
-                                                      0x5, 0x6, 0x17 };
+    const vec_u8 doubleleft = (vec_u8) {0, 1, 2, 3,
+                                        0, 1, 2, 3,
+                                        4, 5, 6, 7,
+                                        4, 5, 6, 7 };
+    const vec_u8 doubleright = (vec_u8) {8, 9, 10, 11,
+                                         8, 9, 10, 11,
+                                         12, 13, 14, 15,
+                                         12, 13, 14, 15 };
+    const vec_u8 perm3rg0 = (vec_u8) {0x0, 0x10, 0,
+                                      0x1, 0x11, 0,
+                                      0x2, 0x12, 0,
+                                      0x3, 0x13, 0,
+                                      0x4, 0x14, 0,
+                                      0x5 };
+    const vec_u8 perm3rg1 = (vec_u8) {     0x15, 0,
+                                      0x6, 0x16, 0,
+                                      0x7, 0x17, 0 };
+    const vec_u8 perm3tb0 = (vec_u8) {0x0, 0x1, 0x10,
+                                      0x3, 0x4, 0x11,
+                                      0x6, 0x7, 0x12,
+                                      0x9, 0xa, 0x13,
+                                      0xc, 0xd, 0x14,
+                                      0xf };
+    const vec_u8 perm3tb1 = (vec_u8) {     0x0, 0x15,
+                                      0x2, 0x3, 0x16,
+                                      0x5, 0x6, 0x17 };
 
     for (i = 0; i < (dstW + 1) >> 1; i += 8) { // The x86 asm also overwrites padding bytes.
         vy = vec_ld(0, &buf0[i * 2]);
@@ -1172,8 +1172,8 @@ yuv2rgb_1_vsx_template(SwsContext *c, const int16_t *buf0,
         vu = vec_ld(0, &ubuf0[i]);
         vv = vec_ld(0, &vbuf0[i]);
         if (uvalpha < 2048) {
-            vu = (vector int16_t) vec_sub((vector uint16_t) vu, sub7);
-            vv = (vector int16_t) vec_sub((vector uint16_t) vv, sub7);
+            vu = (vec_s16) vec_sub((vec_u16) vu, sub7);
+            vv = (vec_s16) vec_sub((vec_u16) vv, sub7);
 
             tmp32 = vec_mule(vu, mul4);
             tmp32_2 = vec_mulo(vu, mul4);
@@ -1186,10 +1186,10 @@ yuv2rgb_1_vsx_template(SwsContext *c, const int16_t *buf0,
         } else {
             tmp16 = vec_ld(0, &ubuf1[i]);
             vu = vec_add(vu, tmp16);
-            vu = (vector int16_t) vec_sub((vector uint16_t) vu, sub8);
+            vu = (vec_s16) vec_sub((vec_u16) vu, sub8);
             tmp16 = vec_ld(0, &vbuf1[i]);
             vv = vec_add(vv, tmp16);
-            vv = (vector int16_t) vec_sub((vector uint16_t) vv, sub8);
+            vv = (vec_s16) vec_sub((vec_u16) vv, sub8);
 
             vu32_l = vec_mule(vu, mul8);
             vu32_r = vec_mulo(vu, mul8);
@@ -1202,7 +1202,7 @@ yuv2rgb_1_vsx_template(SwsContext *c, const int16_t *buf0,
             A = vec_add(A, add64);
             A = vec_sr(A, shift7);
             A = vec_max(A, max255);
-            ad = vec_packsu(A, (vector int16_t) zero16);
+            ad = vec_packsu(A, (vec_s16) zero16);
         } else {
             ad = vec_splats((uint8_t) 255);
         }
@@ -1358,41 +1358,41 @@ YUV2RGBWRAPPERX(yuv2, rgb_full, rgb24_full,  AV_PIX_FMT_RGB24, 0)
 YUV2RGBWRAPPERX(yuv2, rgb_full, bgr24_full,  AV_PIX_FMT_BGR24, 0)
 
 static av_always_inline void
-write422(const vector int16_t vy1, const vector int16_t vy2,
-         const vector int16_t vu, const vector int16_t vv,
+write422(const vec_s16 vy1, const vec_s16 vy2,
+         const vec_s16 vu, const vec_s16 vv,
          uint8_t *dest, const enum AVPixelFormat target)
 {
-    vector uint8_t vd1, vd2, tmp;
-    const vector uint8_t yuyv1 = (vector uint8_t) {
-                                 0x0, 0x10, 0x1, 0x18,
-                                 0x2, 0x11, 0x3, 0x19,
-                                 0x4, 0x12, 0x5, 0x1a,
-                                 0x6, 0x13, 0x7, 0x1b };
-    const vector uint8_t yuyv2 = (vector uint8_t) {
-                                 0x8, 0x14, 0x9, 0x1c,
-                                 0xa, 0x15, 0xb, 0x1d,
-                                 0xc, 0x16, 0xd, 0x1e,
-                                 0xe, 0x17, 0xf, 0x1f };
-    const vector uint8_t yvyu1 = (vector uint8_t) {
-                                 0x0, 0x18, 0x1, 0x10,
-                                 0x2, 0x19, 0x3, 0x11,
-                                 0x4, 0x1a, 0x5, 0x12,
-                                 0x6, 0x1b, 0x7, 0x13 };
-    const vector uint8_t yvyu2 = (vector uint8_t) {
-                                 0x8, 0x1c, 0x9, 0x14,
-                                 0xa, 0x1d, 0xb, 0x15,
-                                 0xc, 0x1e, 0xd, 0x16,
-                                 0xe, 0x1f, 0xf, 0x17 };
-    const vector uint8_t uyvy1 = (vector uint8_t) {
-                                 0x10, 0x0, 0x18, 0x1,
-                                 0x11, 0x2, 0x19, 0x3,
-                                 0x12, 0x4, 0x1a, 0x5,
-                                 0x13, 0x6, 0x1b, 0x7 };
-    const vector uint8_t uyvy2 = (vector uint8_t) {
-                                 0x14, 0x8, 0x1c, 0x9,
-                                 0x15, 0xa, 0x1d, 0xb,
-                                 0x16, 0xc, 0x1e, 0xd,
-                                 0x17, 0xe, 0x1f, 0xf };
+    vec_u8 vd1, vd2, tmp;
+    const vec_u8 yuyv1 = (vec_u8) {
+                         0x0, 0x10, 0x1, 0x18,
+                         0x2, 0x11, 0x3, 0x19,
+                         0x4, 0x12, 0x5, 0x1a,
+                         0x6, 0x13, 0x7, 0x1b };
+    const vec_u8 yuyv2 = (vec_u8) {
+                         0x8, 0x14, 0x9, 0x1c,
+                         0xa, 0x15, 0xb, 0x1d,
+                         0xc, 0x16, 0xd, 0x1e,
+                         0xe, 0x17, 0xf, 0x1f };
+    const vec_u8 yvyu1 = (vec_u8) {
+                         0x0, 0x18, 0x1, 0x10,
+                         0x2, 0x19, 0x3, 0x11,
+                         0x4, 0x1a, 0x5, 0x12,
+                         0x6, 0x1b, 0x7, 0x13 };
+    const vec_u8 yvyu2 = (vec_u8) {
+                         0x8, 0x1c, 0x9, 0x14,
+                         0xa, 0x1d, 0xb, 0x15,
+                         0xc, 0x1e, 0xd, 0x16,
+                         0xe, 0x1f, 0xf, 0x17 };
+    const vec_u8 uyvy1 = (vec_u8) {
+                         0x10, 0x0, 0x18, 0x1,
+                         0x11, 0x2, 0x19, 0x3,
+                         0x12, 0x4, 0x1a, 0x5,
+                         0x13, 0x6, 0x1b, 0x7 };
+    const vec_u8 uyvy2 = (vec_u8) {
+                         0x14, 0x8, 0x1c, 0x9,
+                         0x15, 0xa, 0x1d, 0xb,
+                         0x16, 0xc, 0x1e, 0xd,
+                         0x17, 0xe, 0x1f, 0xf };
 
     vd1 = vec_packsu(vy1, vy2);
     vd2 = vec_packsu(vu, vv);
@@ -1428,11 +1428,11 @@ yuv2422_X_vsx_template(SwsContext *c, const int16_t *lumFilter,
                      int y, enum AVPixelFormat target)
 {
     int i, j;
-    vector int16_t vy1, vy2, vu, vv;
-    vector int32_t vy32[4], vu32[2], vv32[2], tmp, tmp2, tmp3, tmp4;
-    vector int16_t vlumFilter[MAX_FILTER_SIZE], vchrFilter[MAX_FILTER_SIZE];
-    const vector int32_t start = vec_splats(1 << 18);
-    const vector uint32_t shift19 = vec_splats(19U);
+    vec_s16 vy1, vy2, vu, vv;
+    vec_s32 vy32[4], vu32[2], vv32[2], tmp, tmp2, tmp3, tmp4;
+    vec_s16 vlumFilter[MAX_FILTER_SIZE], vchrFilter[MAX_FILTER_SIZE];
+    const vec_s32 start = vec_splats(1 << 18);
+    const vec_u32 shift19 = vec_splats(19U);
 
     for (i = 0; i < lumFilterSize; i++)
         vlumFilter[i] = vec_splats(lumFilter[i]);
@@ -1539,11 +1539,11 @@ yuv2422_2_vsx_template(SwsContext *c, const int16_t *buf[2],
                   *vbuf0 = vbuf[0], *vbuf1 = vbuf[1];
     const int16_t  yalpha1 = 4096 - yalpha;
     const int16_t uvalpha1 = 4096 - uvalpha;
-    vector int16_t vy1, vy2, vu, vv;
-    vector int32_t tmp, tmp2, tmp3, tmp4, tmp5, tmp6;
-    const vector int16_t vyalpha1 = vec_splats(yalpha1);
-    const vector int16_t vuvalpha1 = vec_splats(uvalpha1);
-    const vector uint32_t shift19 = vec_splats(19U);
+    vec_s16 vy1, vy2, vu, vv;
+    vec_s32 tmp, tmp2, tmp3, tmp4, tmp5, tmp6;
+    const vec_s16 vyalpha1 = vec_splats(yalpha1);
+    const vec_s16 vuvalpha1 = vec_splats(uvalpha1);
+    const vec_u32 shift19 = vec_splats(19U);
     int i;
     av_assert2(yalpha  <= 4096U);
     av_assert2(uvalpha <= 4096U);
@@ -1568,11 +1568,11 @@ yuv2422_1_vsx_template(SwsContext *c, const int16_t *buf0,
                      int uvalpha, int y, enum AVPixelFormat target)
 {
     const int16_t *ubuf0 = ubuf[0], *vbuf0 = vbuf[0];
-    vector int16_t vy1, vy2, vu, vv, tmp;
-    const vector int16_t add64 = vec_splats((int16_t) 64);
-    const vector int16_t add128 = vec_splats((int16_t) 128);
-    const vector uint16_t shift7 = vec_splat_u16(7);
-    const vector uint16_t shift8 = vec_splat_u16(8);
+    vec_s16 vy1, vy2, vu, vv, tmp;
+    const vec_s16 add64 = vec_splats((int16_t) 64);
+    const vec_s16 add128 = vec_splats((int16_t) 128);
+    const vec_u16 shift7 = vec_splat_u16(7);
+    const vec_u16 shift8 = vec_splat_u16(8);
     int i;
 
     if (uvalpha < 2048) {
@@ -1666,18 +1666,18 @@ static void hyscale_fast_vsx(SwsContext *c, int16_t *dst, int dstWidth,
 {
     int i;
     unsigned int xpos = 0, xx;
-    vector uint8_t vin, vin2, vperm;
-    vector int8_t vmul, valpha;
-    vector int16_t vtmp, vtmp2, vtmp3, vtmp4;
-    vector uint16_t vd_l, vd_r, vcoord16[2];
-    vector uint32_t vcoord[4];
-    const vector uint32_t vadd = (vector uint32_t) {
+    vec_u8 vin, vin2, vperm;
+    vec_s8 vmul, valpha;
+    vec_s16 vtmp, vtmp2, vtmp3, vtmp4;
+    vec_u16 vd_l, vd_r, vcoord16[2];
+    vec_u32 vcoord[4];
+    const vec_u32 vadd = (vec_u32) {
         0,
         xInc * 1,
         xInc * 2,
         xInc * 3,
     };
-    const vector uint16_t vadd16 = (vector uint16_t) { // Modulo math
+    const vec_u16 vadd16 = (vec_u16) { // Modulo math
         0,
         xInc * 1,
         xInc * 2,
@@ -1687,10 +1687,10 @@ static void hyscale_fast_vsx(SwsContext *c, int16_t *dst, int dstWidth,
         xInc * 6,
         xInc * 7,
     };
-    const vector uint32_t vshift16 = vec_splats((uint32_t) 16);
-    const vector uint16_t vshift9 = vec_splat_u16(9);
-    const vector uint8_t vzero = vec_splat_u8(0);
-    const vector uint16_t vshift = vec_splat_u16(7);
+    const vec_u32 vshift16 = vec_splats((uint32_t) 16);
+    const vec_u16 vshift9 = vec_splat_u16(9);
+    const vec_u8 vzero = vec_splat_u8(0);
+    const vec_u16 vshift = vec_splat_u16(7);
 
     for (i = 0; i < dstWidth; i += 16) {
         vcoord16[0] = vec_splats((uint16_t) xpos);
@@ -1701,7 +1701,7 @@ static void hyscale_fast_vsx(SwsContext *c, int16_t *dst, int dstWidth,
 
         vcoord16[0] = vec_sr(vcoord16[0], vshift9);
         vcoord16[1] = vec_sr(vcoord16[1], vshift9);
-        valpha = (vector int8_t) vec_pack(vcoord16[0], vcoord16[1]);
+        valpha = (vec_s8) vec_pack(vcoord16[0], vcoord16[1]);
 
         xx = xpos >> 16;
         vin = vec_vsx_ld(0, &src[xx]);
@@ -1730,22 +1730,22 @@ static void hyscale_fast_vsx(SwsContext *c, int16_t *dst, int dstWidth,
         vin2 = vec_vsx_ld(1, &src[xx]);
         vin2 = vec_perm(vin2, vin2, vperm);
 
-        vmul = (vector int8_t) vec_sub(vin2, vin);
+        vmul = (vec_s8) vec_sub(vin2, vin);
         vtmp = vec_mule(vmul, valpha);
         vtmp2 = vec_mulo(vmul, valpha);
         vtmp3 = vec_mergeh(vtmp, vtmp2);
         vtmp4 = vec_mergel(vtmp, vtmp2);
 
-        vd_l = (vector uint16_t) vec_mergeh(vin, vzero);
-        vd_r = (vector uint16_t) vec_mergel(vin, vzero);
+        vd_l = (vec_u16) vec_mergeh(vin, vzero);
+        vd_r = (vec_u16) vec_mergel(vin, vzero);
         vd_l = vec_sl(vd_l, vshift);
         vd_r = vec_sl(vd_r, vshift);
 
-        vd_l = vec_add(vd_l, (vector uint16_t) vtmp3);
-        vd_r = vec_add(vd_r, (vector uint16_t) vtmp4);
+        vd_l = vec_add(vd_l, (vec_u16) vtmp3);
+        vd_r = vec_add(vd_r, (vec_u16) vtmp4);
 
-        vec_st((vector int16_t) vd_l, 0, &dst[i]);
-        vec_st((vector int16_t) vd_r, 0, &dst[i + 8]);
+        vec_st((vec_s16) vd_l, 0, &dst[i]);
+        vec_st((vec_s16) vd_r, 0, &dst[i + 8]);
 
         xpos += xInc * 16;
     }
@@ -1773,8 +1773,8 @@ static void hyscale_fast_vsx(SwsContext *c, int16_t *dst, int dstWidth,
         vd_l = vec_add(vd_l, vtmp3); \
         vd_r = vec_add(vd_r, vtmp4); \
 \
-        vec_st((vector int16_t) vd_l, 0, &out[i]); \
-        vec_st((vector int16_t) vd_r, 0, &out[i + 8])
+        vec_st((vec_s16) vd_l, 0, &out[i]); \
+        vec_st((vec_s16) vd_r, 0, &out[i + 8])
 
 static void hcscale_fast_vsx(SwsContext *c, int16_t *dst1, int16_t *dst2,
                            int dstWidth, const uint8_t *src1,
@@ -1782,19 +1782,19 @@ static void hcscale_fast_vsx(SwsContext *c, int16_t *dst1, int16_t *dst2,
 {
     int i;
     unsigned int xpos = 0, xx;
-    vector uint8_t vin, vin2, vperm;
-    vector uint8_t valpha, valphaxor;
-    vector uint16_t vtmp, vtmp2, vtmp3, vtmp4;
-    vector uint16_t vd_l, vd_r, vcoord16[2];
-    vector uint32_t vcoord[4];
-    const vector uint8_t vxor = vec_splats((uint8_t) 127);
-    const vector uint32_t vadd = (vector uint32_t) {
+    vec_u8 vin, vin2, vperm;
+    vec_u8 valpha, valphaxor;
+    vec_u16 vtmp, vtmp2, vtmp3, vtmp4;
+    vec_u16 vd_l, vd_r, vcoord16[2];
+    vec_u32 vcoord[4];
+    const vec_u8 vxor = vec_splats((uint8_t) 127);
+    const vec_u32 vadd = (vec_u32) {
         0,
         xInc * 1,
         xInc * 2,
         xInc * 3,
     };
-    const vector uint16_t vadd16 = (vector uint16_t) { // Modulo math
+    const vec_u16 vadd16 = (vec_u16) { // Modulo math
         0,
         xInc * 1,
         xInc * 2,
@@ -1804,8 +1804,8 @@ static void hcscale_fast_vsx(SwsContext *c, int16_t *dst1, int16_t *dst2,
         xInc * 6,
         xInc * 7,
     };
-    const vector uint32_t vshift16 = vec_splats((uint32_t) 16);
-    const vector uint16_t vshift9 = vec_splat_u16(9);
+    const vec_u32 vshift16 = vec_splats((uint32_t) 16);
+    const vec_u16 vshift9 = vec_splat_u16(9);
 
     for (i = 0; i < dstWidth; i += 16) {
         vcoord16[0] = vec_splats((uint16_t) xpos);
@@ -1859,29 +1859,29 @@ static void hScale8To19_vsx(SwsContext *c, int16_t *_dst, int dstW,
 {
     int i, j;
     int32_t *dst = (int32_t *) _dst;
-    vector int16_t vfilter, vin;
-    vector uint8_t vin8;
-    vector int32_t vout;
-    const vector uint8_t vzero = vec_splat_u8(0);
-    const vector uint8_t vunusedtab[8] = {
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0xe, 0xf},
-        (vector uint8_t) {0x0, 0x1, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x10, 0x10, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0x10, 0x10},
+    vec_s16 vfilter, vin;
+    vec_u8 vin8;
+    vec_s32 vout;
+    const vec_u8 vzero = vec_splat_u8(0);
+    const vec_u8 vunusedtab[8] = {
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0xe, 0xf},
+        (vec_u8) {0x0, 0x1, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x10, 0x10, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0x10, 0x10},
     };
-    const vector uint8_t vunused = vunusedtab[filterSize % 8];
+    const vec_u8 vunused = vunusedtab[filterSize % 8];
 
     if (filterSize == 1) {
         for (i = 0; i < dstW; i++) {
@@ -1898,14 +1898,14 @@ static void hScale8To19_vsx(SwsContext *c, int16_t *_dst, int dstW,
             vout = vec_splat_s32(0);
             for (j = 0; j < filterSize; j += 8) {
                 vin8 = vec_vsx_ld(0, &src[srcPos + j]);
-                vin = (vector int16_t) vec_mergeh(vin8, vzero);
+                vin = (vec_s16) vec_mergeh(vin8, vzero);
                 if (j + 8 > filterSize) // Remove the unused elements on the last round
-                    vin = vec_perm(vin, (vector int16_t) vzero, vunused);
+                    vin = vec_perm(vin, (vec_s16) vzero, vunused);
 
                 vfilter = vec_vsx_ld(0, &filter[filterSize * i + j]);
                 vout = vec_msums(vin, vfilter, vout);
             }
-            vout = vec_sums(vout, (vector int32_t) vzero);
+            vout = vec_sums(vout, (vec_s32) vzero);
             dst[i] = FFMIN(vout[3] >> 3, (1 << 19) - 1);
         }
     }
@@ -1921,28 +1921,28 @@ static void hScale16To19_vsx(SwsContext *c, int16_t *_dst, int dstW,
     const uint16_t *src = (const uint16_t *) _src;
     int bits            = desc->comp[0].depth - 1;
     int sh              = bits - 4;
-    vector int16_t vfilter, vin;
-    vector int32_t vout, vtmp, vtmp2, vfilter32_l, vfilter32_r;
-    const vector uint8_t vzero = vec_splat_u8(0);
-    const vector uint8_t vunusedtab[8] = {
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0xe, 0xf},
-        (vector uint8_t) {0x0, 0x1, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x10, 0x10, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0x10, 0x10},
+    vec_s16 vfilter, vin;
+    vec_s32 vout, vtmp, vtmp2, vfilter32_l, vfilter32_r;
+    const vec_u8 vzero = vec_splat_u8(0);
+    const vec_u8 vunusedtab[8] = {
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0xe, 0xf},
+        (vec_u8) {0x0, 0x1, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x10, 0x10, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0x10, 0x10},
     };
-    const vector uint8_t vunused = vunusedtab[filterSize % 8];
+    const vec_u8 vunused = vunusedtab[filterSize % 8];
 
     if ((isAnyRGB(c->srcFormat) || c->srcFormat==AV_PIX_FMT_PAL8) && desc->comp[0].depth<16) {
         sh = 9;
@@ -1966,16 +1966,16 @@ static void hScale16To19_vsx(SwsContext *c, int16_t *_dst, int dstW,
             const int srcPos = filterPos[i];
             vout = vec_splat_s32(0);
             for (j = 0; j < filterSize; j += 8) {
-                vin = (vector int16_t) vec_vsx_ld(0, &src[srcPos + j]);
+                vin = (vec_s16) vec_vsx_ld(0, &src[srcPos + j]);
                 if (j + 8 > filterSize) // Remove the unused elements on the last round
-                    vin = vec_perm(vin, (vector int16_t) vzero, vunused);
+                    vin = vec_perm(vin, (vec_s16) vzero, vunused);
 
                 vfilter = vec_vsx_ld(0, &filter[filterSize * i + j]);
                 vfilter32_l = vec_unpackh(vfilter);
                 vfilter32_r = vec_unpackl(vfilter);
 
-                vtmp = (vector int32_t) vec_mergeh(vin, (vector int16_t) vzero);
-                vtmp2 = (vector int32_t) vec_mergel(vin, (vector int16_t) vzero);
+                vtmp = (vec_s32) vec_mergeh(vin, (vec_s16) vzero);
+                vtmp2 = (vec_s32) vec_mergel(vin, (vec_s16) vzero);
 
                 vtmp = vec_mul(vtmp, vfilter32_l);
                 vtmp2 = vec_mul(vtmp2, vfilter32_r);
@@ -1983,7 +1983,7 @@ static void hScale16To19_vsx(SwsContext *c, int16_t *_dst, int dstW,
                 vout = vec_adds(vout, vtmp);
                 vout = vec_adds(vout, vtmp2);
             }
-            vout = vec_sums(vout, (vector int32_t) vzero);
+            vout = vec_sums(vout, (vec_s32) vzero);
             dst[i] = FFMIN(vout[3] >> sh, (1 << 19) - 1);
         }
     }
@@ -1997,28 +1997,28 @@ static void hScale16To15_vsx(SwsContext *c, int16_t *dst, int dstW,
     int i, j;
     const uint16_t *src = (const uint16_t *) _src;
     int sh              = desc->comp[0].depth - 1;
-    vector int16_t vfilter, vin;
-    vector int32_t vout, vtmp, vtmp2, vfilter32_l, vfilter32_r;
-    const vector uint8_t vzero = vec_splat_u8(0);
-    const vector uint8_t vunusedtab[8] = {
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0xe, 0xf},
-        (vector uint8_t) {0x0, 0x1, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x10, 0x10, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x10, 0x10,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0x10, 0x10, 0x10, 0x10},
-        (vector uint8_t) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
-                          0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0x10, 0x10},
+    vec_s16 vfilter, vin;
+    vec_s32 vout, vtmp, vtmp2, vfilter32_l, vfilter32_r;
+    const vec_u8 vzero = vec_splat_u8(0);
+    const vec_u8 vunusedtab[8] = {
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0xe, 0xf},
+        (vec_u8) {0x0, 0x1, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x10, 0x10, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x10, 0x10,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0x10, 0x10, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0x10, 0x10, 0x10, 0x10},
+        (vec_u8) {0x0, 0x1, 0x2, 0x3, 0x4, 0x5, 0x6, 0x7,
+                  0x8, 0x9, 0xa, 0xb, 0xc, 0xd, 0x10, 0x10},
     };
-    const vector uint8_t vunused = vunusedtab[filterSize % 8];
+    const vec_u8 vunused = vunusedtab[filterSize % 8];
 
     if (sh<15) {
         sh = isAnyRGB(c->srcFormat) || c->srcFormat==AV_PIX_FMT_PAL8 ? 13 : (desc->comp[0].depth - 1);
@@ -2042,16 +2042,16 @@ static void hScale16To15_vsx(SwsContext *c, int16_t *dst, int dstW,
             const int srcPos = filterPos[i];
             vout = vec_splat_s32(0);
             for (j = 0; j < filterSize; j += 8) {
-                vin = (vector int16_t) vec_vsx_ld(0, &src[srcPos + j]);
+                vin = (vec_s16) vec_vsx_ld(0, &src[srcPos + j]);
                 if (j + 8 > filterSize) // Remove the unused elements on the last round
-                    vin = vec_perm(vin, (vector int16_t) vzero, vunused);
+                    vin = vec_perm(vin, (vec_s16) vzero, vunused);
 
                 vfilter = vec_vsx_ld(0, &filter[filterSize * i + j]);
                 vfilter32_l = vec_unpackh(vfilter);
                 vfilter32_r = vec_unpackl(vfilter);
 
-                vtmp = (vector int32_t) vec_mergeh(vin, (vector int16_t) vzero);
-                vtmp2 = (vector int32_t) vec_mergel(vin, (vector int16_t) vzero);
+                vtmp = (vec_s32) vec_mergeh(vin, (vec_s16) vzero);
+                vtmp2 = (vec_s32) vec_mergel(vin, (vec_s16) vzero);
 
                 vtmp = vec_mul(vtmp, vfilter32_l);
                 vtmp2 = vec_mul(vtmp2, vfilter32_r);
@@ -2059,7 +2059,7 @@ static void hScale16To15_vsx(SwsContext *c, int16_t *dst, int dstW,
                 vout = vec_adds(vout, vtmp);
                 vout = vec_adds(vout, vtmp2);
             }
-            vout = vec_sums(vout, (vector int32_t) vzero);
+            vout = vec_sums(vout, (vec_s32) vzero);
             dst[i] = FFMIN(vout[3] >> sh, (1 << 15) - 1);
         }
     }
