--- src/util/sys_defs.h.orig	2019-10-13 15:32:18 UTC
+++ src/util/sys_defs.h
@@ -30,7 +30,8 @@
 #if defined(FREEBSD2) || defined(FREEBSD3) || defined(FREEBSD4) \
     || defined(FREEBSD5) || defined(FREEBSD6) || defined(FREEBSD7) \
     || defined(FREEBSD8) || defined(FREEBSD9) || defined(FREEBSD10) \
-    || defined(FREEBSD11) \
+    || defined(FREEBSD11) || defined(FREEBSD12) || defined(FREEBSD13) \
+    || defined(FREEBSD14) \
     || defined(BSDI2) || defined(BSDI3) || defined(BSDI4) \
     || defined(OPENBSD2) || defined(OPENBSD3) || defined(OPENBSD4) \
     || defined(OPENBSD5) || defined(OPENBSD6) \
