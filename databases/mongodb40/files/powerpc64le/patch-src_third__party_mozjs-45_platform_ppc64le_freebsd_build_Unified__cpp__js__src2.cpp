--- src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src2.cpp.orig	2020-11-30 15:55:08 UTC
+++ src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src2.cpp
@@ -0,0 +1,55 @@
+#define MOZ_UNIFIED_BUILD
+#include "builtin/ModuleObject.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "builtin/ModuleObject.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "builtin/ModuleObject.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "builtin/Object.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "builtin/Object.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "builtin/Object.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "builtin/Profilers.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "builtin/Profilers.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "builtin/Profilers.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "builtin/Reflect.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "builtin/Reflect.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "builtin/Reflect.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "builtin/ReflectParse.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "builtin/ReflectParse.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "builtin/ReflectParse.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "builtin/SIMD.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "builtin/SIMD.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "builtin/SIMD.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
\ No newline at end of file
