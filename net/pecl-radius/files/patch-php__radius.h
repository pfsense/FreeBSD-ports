--- php_radius.h.orig	2025-11-11 17:22:20 UTC
+++ php_radius.h
@@ -53,6 +53,26 @@ extern zend_module_entry radius_module_entry;
 
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
