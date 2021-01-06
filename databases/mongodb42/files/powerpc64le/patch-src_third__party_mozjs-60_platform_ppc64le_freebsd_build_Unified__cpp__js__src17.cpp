--- src/third_party/mozjs-60/platform/ppc64le/freebsd/build/Unified_cpp_js_src17.cpp.orig	2020-11-25 14:27:55 UTC
+++ src/third_party/mozjs-60/platform/ppc64le/freebsd/build/Unified_cpp_js_src17.cpp
@@ -0,0 +1,55 @@
+#define MOZ_UNIFIED_BUILD
+#include "jit/JitFrames.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/JitFrames.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/JitFrames.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/JitOptions.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/JitOptions.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/JitOptions.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/JitSpewer.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/JitSpewer.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/JitSpewer.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
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
\ No newline at end of file
