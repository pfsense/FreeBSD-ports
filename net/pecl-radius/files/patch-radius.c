--- radius.c.orig	2016-02-15 15:11:50 UTC
+++ radius.c
@@ -49,62 +49,18 @@ any other GPL-like (LGPL, GPL2) License.
 #include <arpa/inet.h>
 #endif
 
-#include "pecl-compat/compat.h"
+#include "radius_arginfo.h"
 
-void _radius_close(zend_resource *res TSRMLS_DC);
-
 static int _init_options(struct rad_attr_options *out, int options, int tag);
 
-#define RADIUS_FETCH_RESOURCE(radh, zv) \
-	radh = (struct rad_handle *)compat_zend_fetch_resource(zv, "rad_handle", le_radius TSRMLS_CC); \
-	if (!radh) { \
-		RETURN_FALSE; \
-	}
 
 /* If you declare any globals in php_radius.h uncomment this:
 ZEND_DECLARE_MODULE_GLOBALS(radius)
 */
 
-/* True global resources - no need for thread safety here */
-static int le_radius;
+zend_class_entry *radius_ce;
+static zend_object_handlers radius_object_handlers;
 
-/* {{{ radius_functions[]
- *
- * Every user visible function must have an entry in radius_functions[].
- */
-zend_function_entry radius_functions[] = {
-	PHP_FE(radius_auth_open,    NULL)
-	PHP_FE(radius_acct_open,    NULL)
-	PHP_FE(radius_close,        NULL)
-	PHP_FE(radius_strerror,     NULL)
-	PHP_FE(radius_config,       NULL)
-	PHP_FE(radius_add_server,	NULL)
-	PHP_FE(radius_create_request,	NULL)
-	PHP_FE(radius_put_string,	NULL)
-	PHP_FE(radius_put_int,	NULL)
-	PHP_FE(radius_put_attr,	NULL)
-	PHP_FE(radius_put_addr,	NULL)
-	PHP_FE(radius_put_vendor_string,	NULL)
-	PHP_FE(radius_put_vendor_int,	NULL)
-	PHP_FE(radius_put_vendor_attr,	NULL)
-	PHP_FE(radius_put_vendor_addr,	NULL)
-	PHP_FE(radius_send_request,	NULL)
-	PHP_FE(radius_get_attr,	NULL)
-	PHP_FE(radius_get_tagged_attr_data, NULL)
-	PHP_FE(radius_get_tagged_attr_tag, NULL)
-	PHP_FE(radius_get_vendor_attr,	NULL)
-	PHP_FE(radius_cvt_addr,	NULL)
-	PHP_FE(radius_cvt_int,	NULL)
-	PHP_FE(radius_cvt_string,	NULL)
-	PHP_FE(radius_salt_encrypt_attr,	NULL)
-	PHP_FE(radius_request_authenticator,	NULL)
-	PHP_FE(radius_server_secret,	NULL)
-	PHP_FE(radius_demangle,	NULL)    
-	PHP_FE(radius_demangle_mppe_key,	NULL)    
-	{NULL, NULL, NULL}	/* Must be the last line in radius_functions[] */
-};
-/* }}} */
-
 /* {{{ radius_module_entry
  */
 zend_module_entry radius_module_entry = {
@@ -112,7 +68,7 @@ zend_module_entry radius_module_entry = {
 	STANDARD_MODULE_HEADER,
 #endif
 	"radius",
-	radius_functions,
+	ext_functions,
 	PHP_MINIT(radius),
 	PHP_MSHUTDOWN(radius),
 	NULL,
@@ -129,12 +85,57 @@ ZEND_GET_MODULE(radius)
 ZEND_GET_MODULE(radius)
 #endif
 
+/* {{{ radius_create_object */
+static zend_object *
+radius_create_object(zend_class_entry *class_type)
+{
+	php_radius *intern = zend_object_alloc(sizeof(php_radius), class_type);
+
+	zend_object_std_init(&intern->std, class_type);
+	object_properties_init(&intern->std, class_type);
+	intern->std.handlers = &radius_object_handlers;
+
+	return &intern->std;
+}
+/* }}} */
+
+/* {{{ radius_free_object */
+static void
+radius_free_object(zend_object *object)
+{
+	php_radius *prad = radius_from_obj(object);
+
+	if (prad->hdl)
+		rad_close(prad->hdl);
+
+	zend_object_std_dtor(&prad->std);
+}
+/* }}} */
+
+/* {{{ radius_get_constructor */
+static zend_function *
+radius_get_constructor(zend_object *object)
+{
+	zend_throw_error(NULL, "Cannot directly construct RadiusHandle");
+	return NULL;
+}
+/* }}} */
+
 /* {{{ PHP_MINIT_FUNCTION
  */
 PHP_MINIT_FUNCTION(radius)
 {
-	le_radius = zend_register_list_destructors_ex(_radius_close, NULL, "rad_handle", module_number);
-#include "radius_init_const.h"
+	radius_ce = register_class_RadiusHandle();
+	radius_ce->create_object = radius_create_object;
+
+	memcpy(&radius_object_handlers, &std_object_handlers, sizeof(zend_object_handlers));
+	radius_object_handlers.offset = XtOffsetOf(php_radius, std);
+	radius_object_handlers.free_obj = radius_free_object;
+	radius_object_handlers.get_constructor = radius_get_constructor;
+	radius_object_handlers.clone_obj = NULL;
+	radius_object_handlers.compare = zend_objects_not_comparable;
+
+	#include "radius_init_const.h"
 	REGISTER_LONG_CONSTANT("RADIUS_MPPE_KEY_LEN", MPPE_KEY_LEN, CONST_PERSISTENT);    
 	return SUCCESS;
 }
@@ -159,45 +160,47 @@ PHP_MINFO_FUNCTION(radius)
 }
 /* }}} */
 
-/* {{{ proto resource radius_auth_open(string arg) */
+/* {{{ proto ressource radius_auth_open(string arg) */
 PHP_FUNCTION(radius_auth_open)
 {
-	struct rad_handle *radh = rad_auth_open();
+	struct rad_handle *hdl;
 
-	if (radh != NULL) {
-		compat_zend_register_resource(return_value, radh, le_radius TSRMLS_CC);
-	} else {
+	ZEND_PARSE_PARAMETERS_NONE();
+
+	if ((hdl = rad_auth_open()) == NULL)
 		RETURN_FALSE;
-	}
+
+	RETURN_RADIUS(hdl);
 }
 /* }}} */
 
-/* {{{ proto resource radius_acct_open(string arg) */
+/* {{{ proto ressource radius_acct_open(string arg) */
 PHP_FUNCTION(radius_acct_open)
 {
-	struct rad_handle *radh = rad_acct_open();
+	struct rad_handle *hdl;
 
-	if (radh != NULL) {
-		compat_zend_register_resource(return_value, radh, le_radius TSRMLS_CC);
-	} else {
+	ZEND_PARSE_PARAMETERS_NONE();
+
+	if ((hdl = rad_acct_open()) == NULL)
 		RETURN_FALSE;
-	}
+
+	RETURN_RADIUS(hdl);
 }
 /* }}} */
 
 /* {{{ proto bool radius_close(radh) */
 PHP_FUNCTION(radius_close)
 {
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &z_radh) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_OBJECT_OF_CLASS_EX(zrad, radius_ce, 0, 1)
+	ZEND_PARSE_PARAMETERS_END();
 
-	/* Fetch the resource to verify it. */
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-	compat_zend_delete_resource(z_radh TSRMLS_CC);
+	zval_ptr_dtor(zrad);
+
+	ZVAL_NULL(zrad);
+
 	RETURN_TRUE;
 }
 /* }}} */
@@ -205,511 +208,528 @@ PHP_FUNCTION(radius_strerror)
 /* {{{ proto string radius_strerror(radh) */
 PHP_FUNCTION(radius_strerror)
 {
-	char *msg;
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &z_radh) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-	msg = (char *)rad_strerror(radh);
-	RETURN_STRINGL(msg, strlen(msg));
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
+		RETURN_EMPTY_STRING();
+
+	RETURN_STRING(rad_strerror(prad->hdl));
 }
 /* }}} */
 
 /* {{{ proto bool radius_config(desc, configfile) */
 PHP_FUNCTION(radius_config)
 {
-	char *filename;
-	COMPAT_ARG_SIZE_T filename_len;
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
+	char *file;
+	size_t file_len;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs", &z_radh, &filename, &filename_len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(2, 2)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_STRING(file, file_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (rad_config(radh, filename) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else {
+
+	if (rad_config(prad->hdl, file) == 0)
 		RETURN_TRUE;
-	}
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_add_server(desc, hostname, port, secret, timeout, maxtries) */
 PHP_FUNCTION(radius_add_server)
 {
-	char *hostname, *secret;
-	COMPAT_ARG_SIZE_T hostname_len, secret_len;
-	long  port, timeout, maxtries;
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
+	char *host, *secret;
+	size_t host_len, secret_len;
+	zend_long port, timeout = 30, max_tries = 5;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rslsll", &z_radh,
-		&hostname, &hostname_len,
-		&port,
-		&secret, &secret_len,
-		&timeout, &maxtries) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(4, 6)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_STRING(host, host_len)
+		Z_PARAM_LONG(port)
+		Z_PARAM_STRING(secret, secret_len)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(timeout)
+		Z_PARAM_LONG(max_tries)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (rad_add_server(radh, hostname, port, secret, timeout, maxtries) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else {
+
+	if (rad_add_server(prad->hdl, host, port, secret, timeout, max_tries) == 0)
 		RETURN_TRUE;
-	}
+
+	RETURN_FALSE;
 }
 /* }}} */
 
-/* {{{ proto bool radius_create_request(desc, code) */
+/* {{{ proto bool radius_create_request(desc, code, msg_auth) */
 PHP_FUNCTION(radius_create_request)
 {
-	long code;
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
+	zend_long code;
+	zend_bool msg_auth = 0;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rl", &z_radh, &code) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(2, 3)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(code)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_BOOL(msg_auth)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (rad_create_request(radh, code) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else {
+
+	if (rad_create_request(prad->hdl, code, msg_auth) == 0)
 		RETURN_TRUE;
-	}
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_string(desc, type, str, options, tag) */
 PHP_FUNCTION(radius_put_string)
 {
-	char *str;
-	COMPAT_ARG_SIZE_T str_len;
-	long type, options = 0, tag = 0;
+	zval *zrad;
+	zend_long type, options = 0, tag = 0;
+	char *value;
+	size_t value_len;
+	php_radius *prad = NULL;
 	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rls|ll", &z_radh, &type, &str, &str_len, &options, &tag)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(3, 5)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(type)
+		Z_PARAM_STRING(value, value_len)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else if (rad_put_string(radh, type, str, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_string(prad->hdl, type, value, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_int(desc, type, int, options, tag) */
 PHP_FUNCTION(radius_put_int)
 {
-	long type, val, options = 0, tag = 0;
+	zval *zrad;
+	zend_long type, value, options = 0, tag = 0;
+	php_radius *prad = NULL;
 	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rll|ll", &z_radh, &type, &val, &options, &tag)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(3, 5)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(type)
+		Z_PARAM_LONG(value)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else if (rad_put_int(radh, type, val, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_int(prad->hdl, type, value, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_attr(desc, type, data, options, tag) */
 PHP_FUNCTION(radius_put_attr)
 {
-	long type, options = 0, tag = 0;
-	COMPAT_ARG_SIZE_T len;
-	char *data;
+	zval *zrad;
+	zend_long type, options = 0, tag = 0;
+	char *value;
+	size_t value_len;
+	php_radius *prad = NULL;
 	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rls|ll", &z_radh, &type, &data, &len, &options, &tag)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(3, 5)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(type)
+		Z_PARAM_STRING(value, value_len)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else if (rad_put_attr(radh, type, data, len, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_attr(prad->hdl, type, value, value_len, &attr_options) == 0))
+		RETURN_TRUE;
 
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_addr(desc, type, addr, options, tag) */
 PHP_FUNCTION(radius_put_addr)
 {
-	COMPAT_ARG_SIZE_T addrlen;
-	long type, options = 0, tag = 0;
-	char	*addr;
-	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
+	zend_long type, options = 0, tag = 0;
+	char *value;
+	size_t value_len;
 	struct in_addr intern_addr;
+	php_radius *prad = NULL;
+	struct rad_attr_options attr_options;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rls|ll", &z_radh, &type, &addr, &addrlen, &options, &tag)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(3, 5)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(type)
+		Z_PARAM_STRING(value, value_len);
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (inet_aton(addr, &intern_addr) == 0) {
-		zend_error(E_ERROR, "Error converting Address");
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	}
 
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (inet_aton(value, &intern_addr) == 0)
 		RETURN_FALSE;
-	} else if (rad_put_addr(radh, type, intern_addr, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_addr(prad->hdl, type, intern_addr, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_vendor_string(desc, vendor, type, str, options, tag) */
 PHP_FUNCTION(radius_put_vendor_string)
 {
-	char *str;
-	COMPAT_ARG_SIZE_T str_len;
-	long type, vendor, options = 0, tag = 0;
+	zval *zrad;
+	zend_long vendor, type, options = 0, tag = 0;
+	char *value;
+	size_t value_len;
 	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rlls|ll", &z_radh, &vendor, &type, &str, &str_len, &options, &tag)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(4, 6)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce);
+		Z_PARAM_LONG(vendor)
+		Z_PARAM_LONG(type)
+		Z_PARAM_STRING(value, value_len)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else if (rad_put_vendor_string(radh, vendor, type, str, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+		&& (rad_put_vendor_string(prad->hdl, vendor, type, value, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_vendor_int(desc, vendor, type, int, options, tag) */
 PHP_FUNCTION(radius_put_vendor_int)
 {
-	long type, vendor, val, options = 0, tag = 0;
+	zval *zrad;
+	zend_long vendor, type, value, options = 0, tag = 0;
+	php_radius *prad = NULL;
 	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rlll|ll", &z_radh, &vendor, &type, &val, &options, &tag)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(4, 6)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(vendor)
+		Z_PARAM_LONG(type)
+		Z_PARAM_LONG(value)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else if (rad_put_vendor_int(radh, vendor, type, val, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_vendor_int(prad->hdl, vendor, type, value, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_vendor_attr(desc, vendor, type, data, options, tag) */
 PHP_FUNCTION(radius_put_vendor_attr)
 {
-	long type, vendor, options = 0, tag = 0;
-	COMPAT_ARG_SIZE_T len;
-	char *data;
+	zval *zrad;
+	zend_long vendor, type, options = 0, tag = 0;
+	char *value;
+	size_t value_len;
+	php_radius *prad = NULL;
 	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rlls|ll", &z_radh, &vendor, &type,
-		&data, &len, &options, &tag) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(4, 6)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(vendor)
+		Z_PARAM_LONG(type)
+		Z_PARAM_STRING(value, value_len)
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else if (rad_put_vendor_attr(radh, vendor, type, data, len, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_vendor_attr(prad->hdl, vendor, type, value, value_len, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_put_vendor_addr(desc, vendor, type, addr) */
 PHP_FUNCTION(radius_put_vendor_addr)
 {
-	long type, vendor, options = 0, tag = 0;
-	COMPAT_ARG_SIZE_T addrlen;
-	char	*addr;
-	struct rad_attr_options attr_options;
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
+	zend_long vendor, type, options = 0, tag = 0;
+	char *value;
+	size_t value_len;
 	struct in_addr intern_addr;
+	php_radius *prad = NULL;
+	struct rad_attr_options attr_options;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rlls|ll", &z_radh, &vendor,
-		&type, &addr, &addrlen, &options, &tag) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(4, 6)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_LONG(vendor)
+		Z_PARAM_LONG(type)
+		Z_PARAM_STRING(value, value_len);
+		Z_PARAM_OPTIONAL
+		Z_PARAM_LONG(options)
+		Z_PARAM_LONG(tag)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	if (inet_aton(addr, &intern_addr) == 0) {
-		zend_error(E_ERROR, "Error converting Address");
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	}
 
-	if (_init_options(&attr_options, options, tag) == -1) {
+	if (inet_aton(value, &intern_addr) == 0)
 		RETURN_FALSE;
-	} else if (rad_put_vendor_addr(radh, vendor, type, intern_addr, &attr_options) == -1) {
-		RETURN_FALSE;
-	}
 
-	RETURN_TRUE;
+	if ((_init_options(&attr_options, options, tag) == 0)
+	    && (rad_put_vendor_addr(prad->hdl, vendor, type, intern_addr, &attr_options) == 0))
+		RETURN_TRUE;
+
+	RETURN_FALSE;
 }
 /* }}} */
 
 /* {{{ proto bool radius_send_request(desc) */
 PHP_FUNCTION(radius_send_request)
 {
-	struct rad_handle *radh;
-	zval *z_radh;
+	zval *zrad;
 	int res;
+	php_radius *prad = NULL;
+	
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+	ZEND_PARSE_PARAMETERS_END();
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &z_radh)
-		== FAILURE) {
-		return;
-	}
-
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	res = rad_send_request(radh);
-	if (res == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else {
-		RETURN_LONG(res);
-	}
+	
+	if ((res = rad_send_request(prad->hdl)) == -1)
+		RETURN_FALSE;
+
+	RETURN_LONG(res);
 }
 /* }}} */
 
 /* {{{ proto string radius_get_attr(desc) */
 PHP_FUNCTION(radius_get_attr)
 {
-	struct rad_handle *radh;
+	zval *zrad, *data;
+	const void *attr;
+	size_t attr_len;
 	int res;
-	const void *data;
-	size_t len;
-	zval *z_radh;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &z_radh) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
+		RETURN_FALSE;
 
-	res = rad_get_attr(radh, &data, &len);
-	if (res == -1) {
+	if ((res = rad_get_attr(prad->hdl, &attr, &attr_len)) == -1)
 		RETURN_FALSE;
-	} else {
-		if (res > 0) {
 
-			array_init(return_value);
-			add_assoc_long(return_value, "attr", res);
-			add_assoc_stringl(return_value, "data", (char *) data, len);
-			return;
-		}
-		RETURN_LONG(res);
+	if (res > 0) {
+		array_init(return_value);
+		add_assoc_long(return_value, "attr", res);
+		add_assoc_stringl(return_value, "data", (char *) attr, attr_len);
+		return;
 	}
+
+	RETURN_LONG(res);
 }
 /* }}} */
 
 /* {{{ proto string radius_get_tagged_attr_data(string attr) */
 PHP_FUNCTION(radius_get_tagged_attr_data)
 {
-	const char *attr;
-	COMPAT_ARG_SIZE_T len;
+	char *value;
+	size_t value_len;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &attr, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_STRING(value, value_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	if (len < 1) {
-		zend_error(E_NOTICE, "Empty attributes cannot have tags");
+	if (value_len < 1)
 		RETURN_FALSE;
-	} else if (len == 1) {
+	
+	if (value_len == 1)
 		RETURN_EMPTY_STRING();
-	}
 
-	RETURN_STRINGL(attr + 1, len - 1);
+	RETURN_STRINGL(value + 1, value_len - 1);
 }
 /* }}} */
 
 /* {{{ proto string radius_get_tagged_attr_tag(string attr) */
 PHP_FUNCTION(radius_get_tagged_attr_tag)
 {
-	const char *attr;
-	COMPAT_ARG_SIZE_T len;
+	char *value;
+	size_t value_len;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &attr, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_STRING(value, value_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	if (len < 1) {
-		zend_error(E_NOTICE, "Empty attributes cannot have tags");
+	if (value_len < 1)
 		RETURN_FALSE;
-	}
-
-	RETURN_LONG((long) *attr);
+	
+	RETURN_LONG((long) *value);
 }
 /* }}} */
 
 /* {{{ proto string radius_get_vendor_attr(data) */
 PHP_FUNCTION(radius_get_vendor_attr)
 {
-	const void *data, *raw;
-	COMPAT_ARG_SIZE_T len;
+	char *value;
+	const void *data;
 	u_int32_t vendor;
 	unsigned char type;
-	size_t data_len;
+	size_t value_len, data_len;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &raw, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_STRING(value, value_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	if (rad_get_vendor_attr(&vendor, &type, &data, &data_len, raw, len) == -1) {
+	if (rad_get_vendor_attr(&vendor, &type, &data, &data_len, value, value_len) == -1)
 		RETURN_FALSE;
-	} else {
-
-		array_init(return_value);
-		add_assoc_long(return_value, "attr", type);
-		add_assoc_long(return_value, "vendor", vendor);
-		add_assoc_stringl(return_value, "data", (char *) data, data_len);
-		return;
-	}
+	
+	array_init(return_value);
+	add_assoc_long(return_value, "attr", type);
+	add_assoc_long(return_value, "vendor", vendor);
+	add_assoc_stringl(return_value, "data", (char *) data, data_len);
 }
 /* }}} */
 
 /* {{{ proto string radius_cvt_addr(data) */
 PHP_FUNCTION(radius_cvt_addr)
 {
-	const void *data;
+	char *data;
+	size_t data_len;
 	char *addr_dot;
-	COMPAT_ARG_SIZE_T len;
 	struct in_addr addr;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &data, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_STRING(data, data_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	addr = rad_cvt_addr(data);
+	addr = rad_cvt_addr((const void *) data);
 	addr_dot = inet_ntoa(addr);
-	RETURN_STRINGL(addr_dot, strlen(addr_dot));
+	RETURN_STRING(addr_dot);
 }
 /* }}} */
 
 /* {{{ proto int radius_cvt_int(data) */
 PHP_FUNCTION(radius_cvt_int)
 {
-	const void *data;
-	COMPAT_ARG_SIZE_T len;
-	int val;
+	char *data;
+	size_t data_len;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &data, &len)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_STRING(data, data_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	val = rad_cvt_int(data);
-	RETURN_LONG(val);
+	RETURN_LONG(rad_cvt_int((const void *) data));
 }
 /* }}} */
 
 /* {{{ proto string radius_cvt_string(data) */
 PHP_FUNCTION(radius_cvt_string)
 {
-	const void *data;
-	char *val;
-	COMPAT_ARG_SIZE_T len;
+	char *data, *val;
+	size_t data_len;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &data, &len)
-		== FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_STRING(data, data_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	val = rad_cvt_string(data, len);
-	if (val == NULL) RETURN_FALSE;
-	RETVAL_STRINGL(val, strlen(val));
+	if ((val = rad_cvt_string((const void *) data, data_len)) == NULL)
+		RETURN_FALSE;
+
+	RETVAL_STRING(val);
 	free(val);
-	return;
 }
 /* }}} */
 
 /* {{{ proto string radius_salt_encrypt_attr(resource radh, string data) */
 PHP_FUNCTION(radius_salt_encrypt_attr)
 {
+	zval *zrad;
 	char *data;
-	COMPAT_ARG_SIZE_T len;
-	struct rad_handle *radh;
+	size_t data_len;
 	struct rad_salted_value salted;
-	zval *z_radh;
+	php_radius *prad;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs", &z_radh, &data, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(2, 2)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_STRING(data, data_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
+		RETURN_FALSE;
 
-	if (rad_salt_value(radh, data, len, &salted) == -1) {
-		zend_error(E_WARNING, "%s", rad_strerror(radh));
+	if (rad_salt_value(prad->hdl, data, data_len, &salted) == -1)
 		RETURN_FALSE;
-	} else if (salted.len == 0) {
+
+	if (salted.len == 0)
 		RETURN_EMPTY_STRING();
-	}
 
 	RETVAL_STRINGL(salted.data, salted.len);
 	efree(salted.data);
@@ -719,105 +739,110 @@ PHP_FUNCTION(radius_request_authenticator)
 /* {{{ proto string radius_request_authenticator(radh) */
 PHP_FUNCTION(radius_request_authenticator)
 {
-	struct rad_handle *radh;
-	ssize_t res;
+	zval *zrad;
 	char buf[LEN_AUTH];
-	zval *z_radh;
+	size_t res_len;
+	php_radius *prad;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &z_radh) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-
-	res = rad_request_authenticator(radh, buf, sizeof buf);
-	if (res == -1) {
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		RETURN_FALSE;
-	} else {
-		RETURN_STRINGL(buf, res);
-	}
+	
+	if ((res_len = rad_request_authenticator(prad->hdl, buf, sizeof(buf))) == -1)
+		RETURN_FALSE;
+
+	RETURN_STRINGL(buf, res_len);
 }
 /* }}} */
 
 /* {{{ proto string radius_server_secret(radh) */
 PHP_FUNCTION(radius_server_secret)
 {
+	zval *zrad;
 	char *secret;
-	struct rad_handle *radh;
-	zval *z_radh;
+	php_radius *prad;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "r", &z_radh) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(1, 1)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
-	secret = (char *)rad_server_secret(radh);
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
+		RETURN_FALSE;
 
-	if (secret) {
-		RETURN_STRINGL(secret, strlen(secret));
-	}
+	if ((secret = (char *) rad_server_secret(prad->hdl)) == NULL)
+		RETURN_FALSE;
 
-	RETURN_FALSE;
+	RETURN_STRING(secret);
 }
 /* }}} */
 
 /* {{{ proto string radius_demangle(radh, mangled) */
 PHP_FUNCTION(radius_demangle)
 {
-	struct rad_handle *radh;
-	zval *z_radh;
-	const void *mangled;
+	zval *zrad;
+	char *mangled;
+	size_t mangled_len;
 	unsigned char *buf;
-	COMPAT_ARG_SIZE_T len;
 	int res;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs", &z_radh, &mangled, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(2, 2)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_STRING(mangled, mangled_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
+	RETVAL_FALSE;
 
-	buf = emalloc(len);
-	res = rad_demangle(radh, mangled, len, buf);
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
+		return;
 
-	if (res == -1) {
-		efree(buf);
-		RETURN_FALSE;
-	} else {
-		RETVAL_STRINGL((char *) buf, len);
-		efree(buf);
+	if ((buf = emalloc(mangled_len)) == NULL)
 		return;
-	}
+
+	if (rad_demangle(prad->hdl, (const void *) mangled, mangled_len, buf) == -1)
+		goto cleanup;
+
+	RETVAL_STRINGL((char *) buf, mangled_len);
+
+cleanup:
+	efree(buf);
 }
 /* }}} */
 
 /* {{{ proto string radius_demangle_mppe_key(radh, mangled) */
 PHP_FUNCTION(radius_demangle_mppe_key)
 {
-	struct rad_handle *radh;
-	zval *z_radh;
-	const void *mangled;
+	zval *zrad;
+	char *mangled;
+	size_t mangled_len;
 	unsigned char *buf;
 	size_t dlen;
-	COMPAT_ARG_SIZE_T len;
 	int res;
+	php_radius *prad = NULL;
 
-	if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rs", &z_radh, &mangled, &len) == FAILURE) {
-		return;
-	}
+	ZEND_PARSE_PARAMETERS_START(2, 2)
+		Z_PARAM_OBJECT_OF_CLASS(zrad, radius_ce)
+		Z_PARAM_STRING(mangled, mangled_len)
+	ZEND_PARSE_PARAMETERS_END();
 
-	RADIUS_FETCH_RESOURCE(radh, z_radh);
+	RETVAL_FALSE;
 
-	buf = emalloc(len);
-	res = rad_demangle_mppe_key(radh, mangled, len, buf, &dlen);
-	if (res == -1) {
-		efree(buf);
-		RETURN_FALSE;
-	} else {
-		RETVAL_STRINGL((char *) buf, dlen);
-		efree(buf);
+	if (((prad = Z_RADIUS_P(zrad)) == NULL) || (prad->hdl == NULL))
 		return;
-	}
+
+	if ((buf = emalloc(mangled_len)) == NULL)
+		return;
+
+	if (rad_demangle_mppe_key(prad->hdl, (const void *) mangled, mangled_len, buf, &dlen) == -1)
+		goto cleanup;
+
+	RETVAL_STRINGL((char *) buf, dlen);
+
+cleanup:
+	efree(buf);
 }
 /* }}} */
 
@@ -842,21 +867,3 @@ int _init_options(struct rad_attr_options *out, int op
 	return 0;
 }
 /* }}} */
-
-/* {{{ _radius_close() */
-void _radius_close(zend_resource *res TSRMLS_DC)
-{
-	struct rad_handle *radh = (struct rad_handle *)res->ptr;
-	rad_close(radh);
-	res->ptr = NULL;
-}
-/* }}} */
-
-/*
- * Local variables:
- * tab-width: 4
- * c-basic-offset: 4
- * End:
- * vim600: noet sw=8 ts=8 fdm=marker
- * vim<600: noet sw=8 ts=8
- */
