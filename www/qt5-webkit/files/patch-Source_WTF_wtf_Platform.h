armv6/v7:
See PR 222612

Add proper architecture name:
  https://gcc.gnu.org/ml/gcc-patches/2015-06/msg01679.html

--- Source/WTF/wtf/Platform.h.orig	2020-03-04 17:16:37 UTC
+++ Source/WTF/wtf/Platform.h
@@ -105,11 +105,14 @@
 
 /* CPU(PPC64) - PowerPC 64-bit Big Endian */
 #if (  defined(__ppc64__)      \
-    || defined(__PPC64__))     \
+    || defined(__PPC64__)      \
+    || defined(__powerpc64__)) \
     && defined(__BYTE_ORDER__) \
     && (__BYTE_ORDER__ == __ORDER_BIG_ENDIAN__)
 #define WTF_CPU_PPC64 1
 #define WTF_CPU_BIG_ENDIAN 1
+#define ENABLE_JIT 0
+#define ENABLE_SAMPLING_PROFILER 0
 #endif
 
 /* CPU(PPC64) - PowerPC 64-bit Little Endian */
@@ -120,6 +123,8 @@
     && defined(__BYTE_ORDER__) \
     && (__BYTE_ORDER__ == __ORDER_LITTLE_ENDIAN__)
 #define WTF_CPU_PPC64LE 1
+#define ENABLE_JIT 0
+#define ENABLE_SAMPLING_PROFILER 0
 #endif
 
 /* CPU(PPC) - PowerPC 32-bit */
@@ -135,6 +141,9 @@
     && (__BYTE_ORDER__ == __ORDER_BIG_ENDIAN__)
 #define WTF_CPU_PPC 1
 #define WTF_CPU_BIG_ENDIAN 1
+#define ENABLE_JIT 0
+#define ENABLE_SAMPLING_PROFILER 0
+#define __GCC_HAVE_SYNC_COMPARE_AND_SWAP_8 1
 #endif
 
 /* CPU(SH4) - SuperH SH-4 */
@@ -227,6 +234,7 @@
 #elif defined(__ARM_ARCH_6__) \
     || defined(__ARM_ARCH_6J__) \
     || defined(__ARM_ARCH_6K__) \
+    || defined(__ARM_ARCH_6KZ__) \
     || defined(__ARM_ARCH_6Z__) \
     || defined(__ARM_ARCH_6ZK__) \
     || defined(__ARM_ARCH_6T2__) \
@@ -273,6 +281,7 @@
 
 #elif defined(__ARM_ARCH_6J__) \
     || defined(__ARM_ARCH_6K__) \
+    || defined(__ARM_ARCH_6KZ__) \
     || defined(__ARM_ARCH_6Z__) \
     || defined(__ARM_ARCH_6ZK__) \
     || defined(__ARM_ARCH_6M__)
