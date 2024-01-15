--- deps/LZMA-SDK/C/CpuArch.c.orig	2022-03-25 08:13:08 UTC
+++ deps/LZMA-SDK/C/CpuArch.c
@@ -384,6 +384,23 @@ BoolInt CPU_IsSupported_AES (void) { return APPLE_CRYP
 
 #include <sys/auxv.h>
 
+#if defined(__FreeBSD__)
+static UInt64 get_hwcap() {
+  unsigned long hwcap;
+  if(elf_aux_info(AT_HWCAP, &hwcap, sizeof(unsigned long)) != 0) {
+        return(0);
+  }
+  return hwcap;
+}
+
+BoolInt CPU_IsSupported_CRC32(void) { return get_hwcap() & HWCAP_CRC32; }
+BoolInt CPU_IsSupported_NEON(void) { return 1; }
+BoolInt CPU_IsSupported_SHA1(void){ return get_hwcap() & HWCAP_SHA1; }
+BoolInt CPU_IsSupported_SHA2(void) { return get_hwcap() & HWCAP_SHA2; }
+BoolInt CPU_IsSupported_AES(void) { return get_hwcap() & HWCAP_AES; }
+
+#else // __FreeBSD__
+
 #define USE_HWCAP
 
 #ifdef USE_HWCAP
@@ -410,6 +427,7 @@ MY_HWCAP_CHECK_FUNC (SHA1)
 MY_HWCAP_CHECK_FUNC (SHA2)
 MY_HWCAP_CHECK_FUNC (AES)
 
+#endif // FreeBSD
 #endif // __APPLE__
 #endif // _WIN32
 
