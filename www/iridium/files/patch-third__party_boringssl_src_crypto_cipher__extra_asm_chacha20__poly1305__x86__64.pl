--- third_party/boringssl/src/crypto/cipher_extra/asm/chacha20_poly1305_x86_64.pl.orig	2023-07-24 14:27:53 UTC
+++ third_party/boringssl/src/crypto/cipher_extra/asm/chacha20_poly1305_x86_64.pl
@@ -449,6 +449,7 @@ $code.="
 .align 64
 chacha20_poly1305_open:
 .cfi_startproc
+    _CET_ENDBR
     push %rbp
 .cfi_push %rbp
     push %rbx
@@ -871,6 +872,7 @@ $code.="
 .align 64
 chacha20_poly1305_seal:
 .cfi_startproc
+    _CET_ENDBR
     push %rbp
 .cfi_push %rbp
     push %rbx
