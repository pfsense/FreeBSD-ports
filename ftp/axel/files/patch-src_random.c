--- src/random.c.orig	2022-11-25 01:30:10 UTC
+++ src/random.c
@@ -6,7 +6,7 @@
 ssize_t
 axel_rand64(uint64_t *out)
 {
-	static int fd = -1;
+	static atomic_int fd = -1;
 	if (fd == -1) {
 		int tmp = open("/dev/random", O_RDONLY);
 		int expect = -1;
