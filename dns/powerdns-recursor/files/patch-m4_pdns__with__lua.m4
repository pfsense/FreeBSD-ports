--- m4/pdns_with_lua.m4.orig	2020-09-30 10:42:53 UTC
+++ m4/pdns_with_lua.m4
@@ -41,7 +41,7 @@ AC_DEFUN([PDNS_WITH_LUA],[
       ], [ : ])
       AS_IF([test -z "$LUAPC"], [
         found_lua=n
-        m4_foreach_w([luapc], [lua5.3 lua-5.3 lua53 lua5.2 lua-5.2 lua52 lua5.1 lua-5.1 lua51 lua], [
+        m4_foreach_w([luapc], [lua5.4 lua-5.4 lua54 lua5.3 lua-5.3 lua53 lua5.2 lua-5.2 lua52 lua5.1 lua-5.1 lua51 lua], [
           AS_IF([test "$found_lua" != "y"], [
             PKG_CHECK_MODULES([LUA], [luapc >= ${lua_min_version}], [
               AC_DEFINE([HAVE_LUA], [1], [Define to 1 if you have lua])
