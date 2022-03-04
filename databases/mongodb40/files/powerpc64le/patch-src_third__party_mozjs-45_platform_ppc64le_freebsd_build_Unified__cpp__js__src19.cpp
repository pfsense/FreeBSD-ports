--- src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src19.cpp.orig	2020-11-30 15:55:08 UTC
+++ src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src19.cpp
@@ -0,0 +1,55 @@
+#define MOZ_UNIFIED_BUILD
+#include "jit/shared/Lowering-shared.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/shared/Lowering-shared.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/shared/Lowering-shared.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jsalloc.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jsalloc.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jsalloc.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jsapi.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jsapi.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jsapi.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jsbool.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jsbool.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jsbool.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jscntxt.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jscntxt.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jscntxt.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jscompartment.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jscompartment.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jscompartment.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
\ No newline at end of file
