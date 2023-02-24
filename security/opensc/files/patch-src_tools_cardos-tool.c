--- src/tools/cardos-tool.c.orig	2023-02-24 16:00:01 UTC
+++ src/tools/cardos-tool.c
@@ -1236,6 +1236,9 @@ end:
 		sc_unlock(card);
 		sc_disconnect_card(card);
 	}
+
+	(void) action_count; /* make compiler happy */
+
 	sc_release_context(ctx);
 	return err;
 }
