--- libpkg/pkg_create.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkg_create.c
@@ -128,13 +128,14 @@ pkg_create_from_dir(struct pkg *pkg, const char *root,
 			}
 
 			if (S_ISLNK(st.st_mode)) {
-				linklen = readlink(fpath, file->symlink_target, sizeof(file->symlink_target) - 1);
+				char link[MAXPATHLEN] = { 0 };
+				linklen = readlink(fpath, link, sizeof(link) -1);
 				if (linklen == -1) {
 					vec_free_and_free(&hardlinks, free);
 					pkg_emit_errno("pkg_create_from_dir", "readlink failed");
 					return (EPKG_FATAL);
 				}
-				file->symlink_target[linklen] = '\0';
+				file->symlink_target = xstrdup(link);
 			}
 
 			if (pc->timestamp > (time_t)-1) {
