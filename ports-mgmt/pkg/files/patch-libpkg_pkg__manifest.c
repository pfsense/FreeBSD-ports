--- libpkg/pkg_manifest.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkg_manifest.c
@@ -1170,10 +1170,10 @@ pkg_emit_object(struct pkg *pkg, short flags)
 						      ucl_object_fromstring(file->sum),
 						      "sum", 0, false);
 				ucl_object_insert_key(file_attrs,
-						      ucl_object_fromstring(file->uname[0] != '\0' ? file->uname : "root"),
+						      ucl_object_fromstring(file->uname != NULL ? file->uname : "root"),
 						      "uname", 0, false);
 				ucl_object_insert_key(file_attrs,
-						      ucl_object_fromstring(file->gname[0] != '\0' ? file->gname : "wheel"),
+						      ucl_object_fromstring(file->gname != NULL ? file->gname : "wheel"),
 						      "gname", 0, false);
 				snprintf(perm_str, sizeof(perm_str), "%#4.4o", file->perm);
 				ucl_object_insert_key(file_attrs,
@@ -1182,7 +1182,7 @@ pkg_emit_object(struct pkg *pkg, short flags)
 				ucl_object_insert_key(file_attrs,
 						      ucl_object_fromint(file->fflags),
 						      "fflags", 0, false);
-				if (file->symlink_target[0] != '\0') {
+				if (file->symlink_target != NULL) {
 					ucl_object_insert_key(file_attrs,
 							      ucl_object_fromstring(file->symlink_target),
 							      "symlink_target", 0, false);
@@ -1220,10 +1220,10 @@ pkg_emit_object(struct pkg *pkg, short flags)
 
 				dir_attrs = ucl_object_typed_new(UCL_OBJECT);
 				ucl_object_insert_key(dir_attrs,
-						      ucl_object_fromstring(dir->uname),
+						      ucl_object_fromstring(dir->uname ? dir->uname : "root"),
 						      "uname", 0, false);
 				ucl_object_insert_key(dir_attrs,
-						      ucl_object_fromstring(dir->gname),
+						      ucl_object_fromstring(dir->gname ? dir->gname : "wheel"),
 						      "gname", 0, false);
 				snprintf(perm_str, sizeof(perm_str), "%#4.4o", dir->perm);
 				ucl_object_insert_key(dir_attrs,
