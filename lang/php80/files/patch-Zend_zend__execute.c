--- Zend/zend_execute.c.orig	2019-03-05 16:57:55 UTC
+++ Zend/zend_execute.c
@@ -1966,7 +1966,7 @@ try_string_offset:
 						ZVAL_NULL(result);
 						return;
 					}
-					zend_error(E_WARNING, "Illegal string offset '%s'", Z_STRVAL_P(dim));
+					zend_error(E_NOTICE, "Illegal string offset '%s'", Z_STRVAL_P(dim));
 					break;
 				case IS_UNDEF:
 					zval_undefined_cv(EX(opline)->op2.var EXECUTE_DATA_CC);
