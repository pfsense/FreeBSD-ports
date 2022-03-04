--- src/mips/ffi.c.orig	2014-11-08 12:47:24 UTC
+++ src/mips/ffi.c
@@ -38,7 +38,9 @@
 #endif
 
 #ifndef USE__BUILTIN___CLEAR_CACHE
-#  if defined(__OpenBSD__)
+#  if defined(__FreeBSD__)
+#    include <machine/sysarch.h>
+#  elif defined(__OpenBSD__)
 #    include <mips64/sysarch.h>
 #  else
 #    include <sys/cachectl.h>
@@ -736,11 +738,13 @@ ffi_prep_closure_loc (ffi_closure *closure,
   closure->fun = fun;
   closure->user_data = user_data;
 
+#if !defined(__FreeBSD__)
 #ifdef USE__BUILTIN___CLEAR_CACHE
   __builtin___clear_cache(clear_location, clear_location + FFI_TRAMPOLINE_SIZE);
 #else
   cacheflush (clear_location, FFI_TRAMPOLINE_SIZE, ICACHE);
 #endif
+#endif /* ! __FreeBSD__ */
   return FFI_OK;
 }
 
