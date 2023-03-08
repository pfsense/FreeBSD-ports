--- src/ptrace/_UPT_access_fpreg.c.orig	2023-03-08 21:34:11 UTC
+++ src/ptrace/_UPT_access_fpreg.c
@@ -104,7 +104,7 @@ _UPT_access_fpreg (unw_addr_space_t as, unw_regnum_t r
 #elif defined(__i386__)
           memcpy(&fpreg.fpr_acc[reg], val, sizeof(unw_fpreg_t));
 #elif defined(__arm__)
-          memcpy(&fpreg.fpr[reg], val, sizeof(unw_fpreg_t));
+          memcpy(&fpreg.fpr_r[reg], val, sizeof(unw_fpreg_t));
 #elif defined(__aarch64__)
           memcpy(&fpreg.fp_q[reg], val, sizeof(unw_fpreg_t));
 #elif defined(__powerpc64__)
@@ -120,7 +120,7 @@ _UPT_access_fpreg (unw_addr_space_t as, unw_regnum_t r
 #elif defined(__i386__)
           memcpy(val, &fpreg.fpr_acc[reg], sizeof(unw_fpreg_t));
 #elif defined(__arm__)
-          memcpy(val, &fpreg.fpr[reg], sizeof(unw_fpreg_t));
+          memcpy(val, &fpreg.fpr_r[reg], sizeof(unw_fpreg_t));
 #elif defined(__aarch64__)
           memcpy(val, &fpreg.fp_q[reg], sizeof(unw_fpreg_t));
 #elif defined(__powerpc64__)
