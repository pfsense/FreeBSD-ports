--- src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src7.cpp.orig	2020-11-30 15:55:08 UTC
+++ src/third_party/mozjs-45/platform/ppc64le/freebsd/build/Unified_cpp_js_src7.cpp
@@ -0,0 +1,55 @@
+#define MOZ_UNIFIED_BUILD
+#include "gc/Tracer.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "gc/Tracer.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "gc/Tracer.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "gc/Verifier.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "gc/Verifier.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "gc/Verifier.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "gc/Zone.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "gc/Zone.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "gc/Zone.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "irregexp/NativeRegExpMacroAssembler.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "irregexp/NativeRegExpMacroAssembler.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "irregexp/NativeRegExpMacroAssembler.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "irregexp/RegExpAST.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "irregexp/RegExpAST.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "irregexp/RegExpAST.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "irregexp/RegExpEngine.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "irregexp/RegExpEngine.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "irregexp/RegExpEngine.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
\ No newline at end of file
