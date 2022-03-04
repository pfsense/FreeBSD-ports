diff --git src/external/krb5.m4 src/external/krb5.m4
index b844c2fbe..856ef56fe 100644
--- src/external/krb5.m4
+++ src/external/krb5.m4
@@ -9,7 +9,7 @@ if test x$KRB5_CFLAGS != x; then
     KRB5_PASSED_CFLAGS=$KRB5_CFLAGS
 fi
 
-AC_PATH_TOOL(KRB5_CONFIG, krb5-config)
+AC_PATH_TOOL(KRB5_CONFIG, krb5-config, [], [/usr/local/bin:$PATH])
 AC_MSG_CHECKING(for working krb5-config)
 if test -x "$KRB5_CONFIG"; then
   KRB5_CFLAGS="`$KRB5_CONFIG --cflags`"
