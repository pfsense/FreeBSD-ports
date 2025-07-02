--- auth.c.orig	2017-02-28 19:06:15 UTC
+++ auth.c
@@ -66,10 +66,10 @@
 #include <string.h>
 #include <errno.h>
 
-#include <dhcp6.h>
-#include <config.h>
-#include <common.h>
-#include <auth.h>
+#include "dhcp6.h"
+#include "config.h"
+#include "common.h"
+#include "auth.h"
 
 #define PADLEN 64
 #define IPAD 0x36
@@ -78,9 +78,9 @@
 #define HMACMD5_KEYLENGTH 64
 
 typedef struct {
-	u_int32_t buf[4];
-	u_int32_t bytes[2];
-	u_int32_t in[16];
+	uint32_t buf[4];
+	uint32_t bytes[2];
+	uint32_t in[16];
 } md5_t;
 
 typedef struct {
@@ -88,22 +88,21 @@ typedef struct {
 	unsigned char key[HMACMD5_KEYLENGTH];
 } hmacmd5_t;
 
-static void hmacmd5_init __P((hmacmd5_t *, const unsigned char *,
-    unsigned int));
-static void hmacmd5_invalidate __P((hmacmd5_t *));
-static void hmacmd5_update __P((hmacmd5_t *, const unsigned char *,
-    unsigned int));
-static void hmacmd5_sign __P((hmacmd5_t *, unsigned char *));
-static int hmacmd5_verify __P((hmacmd5_t *, unsigned char *));
+static void hmacmd5_init(hmacmd5_t *, const unsigned char *,
+    unsigned int);
+static void hmacmd5_invalidate(hmacmd5_t *);
+static void hmacmd5_update(hmacmd5_t *, const unsigned char *,
+    unsigned int);
+static void hmacmd5_sign(hmacmd5_t *, unsigned char *);
+static int hmacmd5_verify(hmacmd5_t *, unsigned char *);
 
-static void md5_init __P((md5_t *));
-static void md5_invalidate __P((md5_t *));
-static void md5_final __P((md5_t *, unsigned char *));
-static void md5_update __P((md5_t *, const unsigned char *, unsigned int));
+static void md5_init(md5_t *);
+static void md5_invalidate(md5_t *);
+static void md5_final(md5_t *, unsigned char *);
+static void md5_update(md5_t *, const unsigned char *, unsigned int);
 
 int
-dhcp6_validate_key(key)
-	struct keyinfo *key;
+dhcp6_validate_key(struct keyinfo *key)
 {
 	time_t now;
 
@@ -120,11 +119,8 @@ dhcp6_validate_key(key)
 }
 
 int
-dhcp6_calc_mac(buf, len, proto, alg, off, key)
-	char *buf;
-	size_t len, off;
-	int proto, alg;
-	struct keyinfo *key;
+dhcp6_calc_mac(char *buf, size_t len, int proto __attribute__((__unused__)), int alg,
+    size_t off, struct keyinfo *key)
 {
 	hmacmd5_t ctx;
 	unsigned char digest[MD5_DIGESTLENGTH];
@@ -152,12 +148,8 @@ dhcp6_calc_mac(buf, len, proto, alg, off, key)
 }
 
 int
-dhcp6_verify_mac(buf, len, proto, alg, off, key)
-	char *buf;
-	ssize_t len;
-	int proto, alg;
-	size_t off;
-	struct keyinfo *key;
+dhcp6_verify_mac(char *buf, ssize_t len, int proto __attribute__((__unused__)),
+    int alg, size_t off, struct keyinfo *key)
 {
 	hmacmd5_t ctx;
 	unsigned char digest[MD5_DIGESTLENGTH];
@@ -168,7 +160,7 @@ dhcp6_verify_mac(buf, len, proto, alg, off, key)
 	if (alg != DHCP6_AUTHALG_HMACMD5)
 		return (-1);
 
-	if (off + MD5_DIGESTLENGTH > len)
+	if (off + MD5_DIGESTLENGTH > (size_t)len)
 		return (-1);
 
 	/*
@@ -287,12 +279,12 @@ hmacmd5_verify(hmacmd5_t *ctx, unsigned char *digest) 
  */
 
 static void
-byteSwap(u_int32_t *buf, unsigned words)
+byteSwap(uint32_t *buf, unsigned words)
 {
 	unsigned char *p = (unsigned char *)buf;
 
 	do {
-		*buf++ = (u_int32_t)((unsigned)p[3] << 8 | p[2]) << 16 |
+		*buf++ = (uint32_t)((unsigned)p[3] << 8 | p[2]) << 16 |
 			((unsigned)p[1] << 8 | p[0]);
 		p += 4;
 	} while (--words);
@@ -338,8 +330,8 @@ md5_invalidate(md5_t *ctx)
  * the data and converts bytes into longwords for this routine.
  */
 static void
-transform(u_int32_t buf[4], u_int32_t const in[16]) {
-	register u_int32_t a, b, c, d;
+transform(uint32_t buf[4], uint32_t const in[16]) {
+	register uint32_t a, b, c, d;
 
 	a = buf[0];
 	b = buf[1];
@@ -427,7 +419,7 @@ transform(u_int32_t buf[4], u_int32_t const in[16]) {
 static void
 md5_update(md5_t *ctx, const unsigned char *buf, unsigned int len)
 {
-	u_int32_t t;
+	uint32_t t;
 
 	/* Update byte count */
 
