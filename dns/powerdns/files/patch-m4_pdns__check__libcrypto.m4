--- m4/pdns_check_libcrypto.m4.orig	2023-06-01 06:54:16 UTC
+++ m4/pdns_check_libcrypto.m4
@@ -75,8 +75,10 @@ AC_DEFUN([PDNS_CHECK_LIBCRYPTO], [
         for ssldir in $ssldirs; do
             AC_MSG_CHECKING([for openssl/crypto.h in $ssldir])
             if test -f "$ssldir/include/openssl/crypto.h"; then
-                LIBCRYPTO_INCLUDES="-I$ssldir/include"
-                LIBCRYPTO_LDFLAGS="-L$ssldir/lib"
+                if test $ssldir != /usr; then
+                    LIBCRYPTO_INCLUDES="-I$ssldir/include"
+                    LIBCRYPTO_LDFLAGS="-L$ssldir/lib"
+                fi
                 LIBCRYPTO_LIBS="-lcrypto"
                 found=true
