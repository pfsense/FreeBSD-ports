--- radlib_md5.h.orig	2025-11-11 17:21:10 UTC
+++ radlib_md5.h
@@ -31,6 +31,9 @@ any other GPL-like (LGPL, GPL2) License.
     $Id$
 */
 
+#ifndef _RADLIB_MD5_H
+#define _RADLIB_MD5_H
+
 #include "php.h"
 #include "ext/standard/md5.h"
 
@@ -39,4 +42,4 @@ any other GPL-like (LGPL, GPL2) License.
 #define MD5Final PHP_MD5Final
 #define MD5_CTX PHP_MD5_CTX
 
-/* vim: set ts=8 sw=8 noet: */
+#endif /* _RADLIB_MD5_H */
