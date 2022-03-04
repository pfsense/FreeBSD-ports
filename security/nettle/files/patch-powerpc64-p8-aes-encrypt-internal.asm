--- powerpc64/p8/aes-encrypt-internal.asm.orig	2021-06-06 20:06:30 UTC
+++ powerpc64/p8/aes-encrypt-internal.asm
@@ -63,7 +63,7 @@ define(`S7', `v9')
 
 define(`FUNC_ALIGN', `5')
 PROLOGUE(_nettle_aes_encrypt)
- DATA_LOAD_VEC(swap_mask,.swap_mask,r5)
+ DATA_LOAD_VEC(swap_mask,.swap_data,r5)
 
  subi ROUNDS,ROUNDS,1
  srdi LENGTH,LENGTH,4
@@ -328,6 +328,6 @@ EPILOGUE(_nettle_aes_encrypt)
 
  .data
  .align 4
-.swap_mask:
+.swap_data:
 IF_LE(`.byte 8,9,10,11,12,13,14,15,0,1,2,3,4,5,6,7')
 IF_BE(`.byte 3,2,1,0,7,6,5,4,11,10,9,8,15,14,13,12')
