--- php_radius.h.orig	2016-02-15 15:11:50 UTC
+++ php_radius.h
@@ -39,7 +39,7 @@ any other GPL-like (LGPL, GPL2) License.
 
 #define phpext_radius_ptr &radius_module_entry
 
-#define PHP_RADIUS_VERSION "1.4.0b1"
+#define PHP_RADIUS_VERSION "1.3.0"
 
 #ifdef PHP_WIN32
 #define PHP_RADIUS_API __declspec(dllexport)
@@ -53,6 +53,26 @@ any other GPL-like (LGPL, GPL2) License.
 
 extern zend_module_entry radius_module_entry;
 
+typedef struct {
+    struct rad_handle *hdl;
+    zend_object std;
+} php_radius;
+
+static inline php_radius *radius_from_obj(zend_object *obj)
+{
+    return (php_radius *)((char *)(obj) - XtOffsetOf(php_radius, std));
+}
+
+#define Z_RADIUS_P(zv) radius_from_obj(Z_OBJ_P(zv))
+
+#define ZVAL_RADIUS(zv, rh) do {    \
+   object_init_ex(zv, radius_ce);   \
+	php_radius *_p = Z_RADIUS_P(zv); \
+	_p->hdl = rh;							\
+	} while (0)
+#define RETVAL_RADIUS(rh) ZVAL_RADIUS(return_value, rh)
+#define RETURN_RADIUS(rh) do { RETVAL_RADIUS(rh); return; } while (0)
+
 PHP_MINIT_FUNCTION(radius);
 PHP_MSHUTDOWN_FUNCTION(radius);
 PHP_MINFO_FUNCTION(radius);
@@ -97,14 +117,3 @@ PHP_FUNCTION(radius_demangle_mppe_key);
 #define RADIUS_OPTION_SALT	RAD_OPTION_SALT
 
 #endif	/* PHP_RADIUS_H */
-
-
-/*
- * Local variables:
- * tab-width: 4
- * c-basic-offset: 4
- * indent-tabs-mode: t
- * End:
- */
-
-/* vim: set ts=8 sw=8 noet: */
