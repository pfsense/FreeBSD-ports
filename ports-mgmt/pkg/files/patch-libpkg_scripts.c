--- libpkg/scripts.c.orig	2020-09-29 18:37:22 UTC
+++ libpkg/scripts.c
@@ -284,7 +284,8 @@ pkg_script_run(struct pkg * const pkg, pkg_script type
 					break;
 			}
 			/* Gather any remaining output */
-			while (!feof(f) && !ferror(f) && getline(&line, &linecap, f) > 0) {
+			while (should_waitpid && !feof(f) && !ferror(f) &&
+			    getline(&line, &linecap, f) > 0) {
 				pkg_emit_message(line);
 			}
 			fclose(f);
