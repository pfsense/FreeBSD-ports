--- ext-src/php_swoole_private.h.orig	2021-10-29 10:11:22 UTC
+++ ext-src/php_swoole_private.h
@@ -891,7 +891,7 @@ static sw_inline zend_bool sw_zend_is_callable_at_fram
                                                         char **error) {
     zend_string *name;
     zend_bool ret;
-#if PHP_VERSION_ID < 80000
+#if PHP_VERSION_ID < 80001
     ret = zend_is_callable_ex(zcallable, zobject ? Z_OBJ_P(zobject) : NULL, check_flags, &name, fci_cache, error);
 #else
     ret = zend_is_callable_at_frame(zcallable, zobject ? Z_OBJ_P(zobject) : NULL, frame, check_flags, fci_cache, error);
