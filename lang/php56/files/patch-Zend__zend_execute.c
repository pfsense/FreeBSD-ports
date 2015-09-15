--- Zend/zend_execute.c.orig	2014-03-05 04:18:00.000000000 -0600
+++ Zend/zend_execute.c	2014-04-29 08:50:49.000000000 -0500
@@ -1276,7 +1276,7 @@
 								break;
 							}
 							if (type != BP_VAR_IS) {
-								zend_error(E_WARNING, "Illegal string offset '%s'", dim->value.str.val);
+								zend_error(E_NOTICE, "Illegal string offset '%s'", dim->value.str.val);
 							}
 							break;
 						case IS_DOUBLE:
