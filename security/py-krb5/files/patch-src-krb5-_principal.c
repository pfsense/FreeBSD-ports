--- src/krb5/_principal.c.orig	2021-09-13 20:29:39 UTC
+++ src/krb5/_principal.c
@@ -18669,7 +18669,11 @@ if (!__Pyx_RefNanny) {
  * 
  * 
  */
+#if defined(KRB5_PRINCIPAL_PARSE_IGNORE_REALM)
   __pyx_t_4 = __Pyx_PyInt_From_int32_t(KRB5_PRINCIPAL_PARSE_IGNORE_REALM); if (unlikely(!__pyx_t_4)) __PYX_ERR(1, 68, __pyx_L1_error)
+#elif defined(KRB5_PRINCIPAL_PARSE_NO_REALM)
+  __pyx_t_4 = __Pyx_PyInt_From_int32_t(KRB5_PRINCIPAL_PARSE_NO_REALM); if (unlikely(!__pyx_t_4)) __PYX_ERR(1, 68, __pyx_L1_error)
+#endif
   __Pyx_GOTREF(__pyx_t_4);
   if (__Pyx_SetNameInClass(__pyx_t_3, __pyx_n_s_ignore_realm, __pyx_t_4) < 0) __PYX_ERR(1, 68, __pyx_L1_error)
   __Pyx_DECREF(__pyx_t_4); __pyx_t_4 = 0;
