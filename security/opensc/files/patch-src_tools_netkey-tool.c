--- src/tools/netkey-tool.c.orig	2023-02-24 16:09:40 UTC
+++ src/tools/netkey-tool.c
@@ -611,5 +611,7 @@ int main(
 	sc_disconnect_card(card);
 	sc_release_context(ctx);
 
+	(void) debug; /* make compiler happy */
+
 	exit(0);
 }
