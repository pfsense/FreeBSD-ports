--- src/third_party/mozjs-60/platform/aarch64/freebsd/build/Unified_cpp_js_src25.cpp.orig	2019-11-14 10:50:10 UTC
+++ src/third_party/mozjs-60/platform/aarch64/freebsd/build/Unified_cpp_js_src25.cpp
@@ -0,0 +1,55 @@
+#define MOZ_UNIFIED_BUILD
+#include "jit/arm64/vixl/Instrument-vixl.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/arm64/vixl/Instrument-vixl.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/arm64/vixl/Instrument-vixl.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/arm64/vixl/MacroAssembler-vixl.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/arm64/vixl/MacroAssembler-vixl.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/arm64/vixl/MacroAssembler-vixl.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/arm64/vixl/MozAssembler-vixl.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/arm64/vixl/MozAssembler-vixl.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/arm64/vixl/MozAssembler-vixl.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/arm64/vixl/MozInstructions-vixl.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/arm64/vixl/MozInstructions-vixl.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/arm64/vixl/MozInstructions-vixl.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/arm64/vixl/Utils-vixl.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/arm64/vixl/Utils-vixl.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/arm64/vixl/Utils-vixl.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
+#include "jit/shared/Assembler-shared.cpp"
+#ifdef PL_ARENA_CONST_ALIGN_MASK
+#error "jit/shared/Assembler-shared.cpp uses PL_ARENA_CONST_ALIGN_MASK, so it cannot be built in unified mode."
+#undef PL_ARENA_CONST_ALIGN_MASK
+#endif
+#ifdef INITGUID
+#error "jit/shared/Assembler-shared.cpp defines INITGUID, so it cannot be built in unified mode."
+#undef INITGUID
+#endif
\ No newline at end of file
