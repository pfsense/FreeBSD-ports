--- net/http/http_auth_gssapi_posix.h.orig	2019-09-10 11:14:09 UTC
+++ net/http/http_auth_gssapi_posix.h
@@ -19,6 +19,9 @@
 #include <GSS/gssapi.h>
 #elif defined(OS_FREEBSD)
 #include <gssapi/gssapi.h>
+#ifndef GSS_C_DELEG_POLICY_FLAG
+#define GSS_C_DELEG_POLICY_FLAG 32768
+#endif
 #else
 #include <gssapi.h>
 #endif
