--- config.m4.orig	2025-11-11 17:28:51 UTC
+++ config.m4
@@ -5,6 +5,9 @@ dnl Make sure that the comment is aligned:
 dnl Make sure that the comment is aligned:
 [  --enable-radius           Enable radius support])
 
+PHP_ARG_ENABLE(openssl, for OpenSSL support,
+[  --enable-openssl   Include OpenSSL support])
+
 if test "$PHP_RADIUS" != "no"; then
 
   AC_TRY_COMPILE([
@@ -17,4 +20,14 @@ ulongint = 1;
   ])
 
  PHP_NEW_EXTENSION(radius, radius.c radlib.c, $ext_shared)
+fi
+
+if test "$PHP_OPENSSL" != "no"; then
+  AC_CHECK_LIB([crypto], [EVP_md5], [
+    AC_DEFINE(HAVE_OPENSSL, 1, [ ])
+    PHP_ADD_LIBRARY(crypto,, EXT_SHARED_LIBADD)
+    PHP_ADD_INCLUDE(/usr/include/openssl)
+  ], [
+    AC_MSG_ERROR([OpenSSL (libcrypto) not found])
+  ])
 fi
