--- src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src14.cpp.orig	2020-11-30 15:55:08 UTC
+++ src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src14.cpp
@@ -0,0 +1,55 @@
+#define MOZ_UNIFIED_BUILD
+#include "jit/JitcodeMap.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/JitcodeMap.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/JitcodeMap.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/LICM.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/LICM.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/LICM.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/LIR.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/LIR.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/LIR.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/LoopUnroller.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/LoopUnroller.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/LoopUnroller.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/Lowering.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/Lowering.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/Lowering.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/MCallOptimize.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/MCallOptimize.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/MCallOptimize.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
\ No newline at end of file
