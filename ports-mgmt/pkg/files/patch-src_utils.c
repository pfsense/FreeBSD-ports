--- src/utils.c.orig	2025-02-24 14:58:49 UTC
+++ src/utils.c
@@ -339,12 +339,9 @@ print_info(struct pkg * const pkg, uint64_t options)
 		default:
 			outflags |= PKG_MANIFEST_EMIT_UCL;
 		}
-		if (pkg_type(pkg) == PKG_REMOTE)
-			outflags |= PKG_MANIFEST_EMIT_COMPACT;
 
 		pkg_emit_manifest_file(pkg, stdout, outflags);
-		if (outflags & PKG_MANIFEST_EMIT_COMPACT)
-			printf("\n");
+		printf("\n");
 		return;
 	}
 
