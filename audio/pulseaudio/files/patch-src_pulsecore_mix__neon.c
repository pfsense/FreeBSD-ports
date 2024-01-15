--- src/pulsecore/mix_neon.c.orig	2023-10-07 05:45:10 UTC
+++ src/pulsecore/mix_neon.c
@@ -176,8 +176,8 @@ static void pa_mix2_ch4_s16ne_neon(pa_mix_info streams
     int32x4_t sv0, sv1;
 
     __asm__ __volatile__ (
-        "vld1.s32 %h[sv0], [%[lin0]]         \n\t"
-        "vld1.s32 %h[sv1], [%[lin1]]         \n\t"
+        "vld1.s32 {%e[sv0],%f[sv0]}, [%[lin0]]         \n\t"
+        "vld1.s32 {%e[sv1],%f[sv1]}, [%[lin1]]         \n\t"
         : [sv0] "=w" (sv0), [sv1] "=w" (sv1)
         : [lin0] "r" (streams[0].linear), [lin1] "r" (streams[1].linear)
         : /* clobber list */
