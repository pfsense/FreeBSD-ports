--- libpkg/pkgdb.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkgdb.c
@@ -1747,11 +1747,11 @@ pkgdb_register_pkg(struct pkgdb *db, struct pkg *pkg, 
 		sql_arg_t args[] = {
 			SQL_ARG(file->path),
 			SQL_ARG(file->sum),
-			SQL_ARG(_pkgdb_empty_str_null(file->uname)),
-			SQL_ARG(_pkgdb_empty_str_null(file->gname)),
+			SQL_ARG(file->uname),
+			SQL_ARG(file->gname),
 			SQL_ARG(file->perm),
 			SQL_ARG(file->fflags),
-			SQL_ARG(_pkgdb_empty_str_null(file->symlink_target)),
+			SQL_ARG(file->symlink_target),
 			SQL_ARG(file->time[1].tv_sec),
 			SQL_ARG(package_id),
 		};
@@ -1836,8 +1836,8 @@ pkgdb_register_pkg(struct pkgdb *db, struct pkg *pkg, 
 	while (pkg_dirs(pkg, &dir) == EPKG_OK) {
 		sql_arg_t args[] = {
 			SQL_ARG(dir->path),
-			SQL_ARG(_pkgdb_empty_str_null(dir->uname)),
-			SQL_ARG(_pkgdb_empty_str_null(dir->gname)),
+			SQL_ARG(dir->uname),
+			SQL_ARG(dir->gname),
 			SQL_ARG(dir->perm),
 			SQL_ARG(dir->fflags),
 			SQL_ARG(dir->time[1].tv_sec),
