--- deps/openssl/config/bn_conf_no-asm.h.orig	2019-05-28 21:32:16 UTC
+++ deps/openssl/config/bn_conf_no-asm.h
@@ -23,8 +23,8 @@
 # include "./archs/VC-WIN64A/no-asm/crypto/include/internal/bn_conf.h"
 #elif defined(_WIN32) && defined(_M_ARM64)
 # include "./archs/VC-WIN64-ARM/no-asm/crypto/include/internal/bn_conf.h"
-#elif (defined(__FreeBSD__) || defined(__OpenBSD__)) && defined(__i386__)
-# include "./archs/BSD-x86/no-asm/crypto/include/internal/bn_conf.h"
+//#elif (defined(__FreeBSD__) || defined(__OpenBSD__)) && defined(__i386__)
+//# include "./archs/BSD-x86/no-asm/crypto/include/internal/bn_conf.h"
 #elif (defined(__FreeBSD__) || defined(__OpenBSD__)) && defined(__x86_64__)
 # include "./archs/BSD-x86_64/no-asm/crypto/include/internal/bn_conf.h"
 #elif defined(__sun) && defined(__i386__)
