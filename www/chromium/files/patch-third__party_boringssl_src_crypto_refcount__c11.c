--- third_party/boringssl/src/crypto/refcount_c11.c.orig	2021-06-10 13:07:35 UTC
+++ third_party/boringssl/src/crypto/refcount_c11.c
@@ -24,6 +24,10 @@
 
 #include <openssl/type_check.h>
 
+#if !defined(__cplusplus) && !defined(static_assert)
+#define static_assert _Static_assert
+#endif
+
 
 // See comment above the typedef of CRYPTO_refcount_t about these tests.
 static_assert(alignof(CRYPTO_refcount_t) == alignof(_Atomic CRYPTO_refcount_t),
