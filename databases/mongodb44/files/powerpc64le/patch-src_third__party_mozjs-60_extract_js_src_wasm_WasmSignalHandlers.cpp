--- src/third_party/mozjs-60/extract/js/src/wasm/WasmSignalHandlers.cpp.orig	2020-11-24 21:49:40 UTC
+++ src/third_party/mozjs-60/extract/js/src/wasm/WasmSignalHandlers.cpp
@@ -263,6 +263,10 @@ struct AutoSignalHandler
 #  define EPC_sig(p) ((p)->uc_mcontext.mc_pc)
 #  define RFP_sig(p) ((p)->uc_mcontext.mc_regs[30])
 # endif
+# if defined(__FreeBSD__) && defined(__powerpc64__)
+#  define R01_sig(p) ((p)->uc_mcontext.mc_frame[1])
+#  define R32_sig(p) ((p)->uc_mcontext.mc_srr0)
+# endif
 #elif defined(XP_DARWIN)
 # define EIP_sig(p) ((p)->uc_mcontext->__ss.__eip)
 # define EBP_sig(p) ((p)->uc_mcontext->__ss.__ebp)
