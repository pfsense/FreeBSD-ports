--- base64.c.orig	2017-02-28 19:06:15 UTC
+++ base64.c
@@ -67,6 +67,8 @@ typedef struct {
 	char *dstend;		/* End of the buffer */
 } base64_decode_ctx_t;
 
+int base64_decodestring(const char *, char *, size_t);
+
 static int
 mem_tobuffer(base64_decode_ctx_t *ctx, void *base, unsigned int length)
 {
