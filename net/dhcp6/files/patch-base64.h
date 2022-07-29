--- base64.h.orig	2017-02-28 19:06:15 UTC
+++ base64.h
@@ -29,4 +29,9 @@
  * SUCH DAMAGE.
  */
 
-extern int base64_decodestring __P((const char *, char *, size_t));
+#ifndef	_BASE64_H_
+#define	_BASE64_H_
+
+int base64_decodestring(const char *, char *, size_t);
+
+#endif
