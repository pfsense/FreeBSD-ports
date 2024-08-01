--- src/ucl_emitter.c.orig	2024-05-14 22:43:47 UTC
+++ src/ucl_emitter.c
@@ -363,7 +363,7 @@ ucl_emitter_common_start_object (struct ucl_emitter_co
 					}
 				}
 				ucl_add_tabs (func, ctx->indent, compact);
-				ucl_emitter_common_start_array (ctx, cur, first_key, true, compact);
+				ucl_emitter_common_start_array (ctx, cur, true, true, compact);
 				ucl_emitter_common_end_array (ctx, cur, compact);
 			}
 			else {
