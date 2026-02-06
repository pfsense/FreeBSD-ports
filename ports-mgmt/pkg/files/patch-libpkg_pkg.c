--- libpkg/pkg.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkg.c
@@ -75,6 +75,7 @@ pkg_free(struct pkg *pkg)
 	free(pkg->repourl);
 	free(pkg->reason);
 	free(pkg->dep_formula);
+	free(pkg->rootpath);
 
 	for (int i = 0; i < PKG_NUM_SCRIPTS; i++)
 		xstring_free(pkg->scripts[i]);
@@ -524,16 +525,16 @@ pkg_addfile_attr(struct pkg *pkg, const char *path, co
 	}
 
 	f = xcalloc(1, sizeof(*f));
-	strlcpy(f->path, path, sizeof(f->path));
+	f->path = xstrdup(path);
 
 	if (sum != NULL)
 		f->sum = xstrdup(sum);
 
 	if (uname != NULL)
-		strlcpy(f->uname, uname, sizeof(f->uname));
+		f->uname = xstrdup(uname);
 
 	if (gname != NULL)
-		strlcpy(f->gname, gname, sizeof(f->gname));
+		f->gname = xstrdup(gname);
 
 	if (perm != 0)
 		f->perm = perm;
@@ -542,7 +543,7 @@ pkg_addfile_attr(struct pkg *pkg, const char *path, co
 		f->fflags = fflags;
 
 	if (symlink_target != NULL)
-		strlcpy(f->symlink_target, symlink_target, sizeof(f->symlink_target));
+		f->symlink_target = xstrdup(symlink_target);
 
 	if (mtime > 0)
 		f->time[1].tv_sec = mtime;
@@ -567,7 +568,7 @@ pkg_addconfig_file(struct pkg *pkg, const char *path, 
 		return (EPKG_FATAL);
 	}
 	f = xcalloc(1, sizeof(*f));
-	strlcpy(f->path, path, sizeof(f->path));
+	f->path = xstrdup(path);
 
 	if (content != NULL)
 		f->content = xstrdup(content);
@@ -633,13 +634,13 @@ pkg_adddir_attr(struct pkg *pkg, const char *path, con
 	}
 
 	d = xcalloc(1, sizeof(*d));
-	strlcpy(d->path, path, sizeof(d->path));
+	d->path = xstrdup(path);
 
 	if (uname != NULL)
-		strlcpy(d->uname, uname, sizeof(d->uname));
+		d->uname = xstrdup(uname);
 
 	if (gname != NULL)
-		strlcpy(d->gname, gname, sizeof(d->gname));
+		d->gname = xstrdup(gname);
 
 	if (perm != 0)
 		d->perm = perm;
@@ -1141,7 +1142,7 @@ pkg_list_free(struct pkg *pkg, pkg_list list)  {
 		pkg->flags &= ~PKG_LOAD_FILES;
 		break;
 	case PKG_DIRS:
-		DL_FREE(pkg->dirs, free);
+		DL_FREE(pkg->dirs, pkg_dir_free);
 		pkghash_destroy(pkg->dirhash);
 		pkg->dirhash = NULL;
 		pkg->flags &= ~PKG_LOAD_DIRS;
@@ -1440,7 +1441,8 @@ pkg_check_meta(struct stat *st, const char *uname, con
 
 	if (S_ISLNK(st->st_mode) != (fs_symlink_target[0] != '\0'))
 		file_status |= FILE_META_MISMATCH_SYMLINK;
-	else if (S_ISLNK(st->st_mode) && strcmp(db_symlink_target, fs_symlink_target) != 0)
+	else if (S_ISLNK(st->st_mode) &&
+	    strcmp(db_symlink_target ? db_symlink_target : "", fs_symlink_target) != 0)
 		file_status |= FILE_META_MISMATCH_SYMLINK;
 
 	return file_status;
@@ -1506,7 +1508,7 @@ emit_status:
 
 	if (file_status & FILE_META_MISMATCH_TYPE) {
 		pkg_emit_file_meta_mismatch(pkg, f, PKG_META_ATTR_TYPE,
-					    f->symlink_target[0] == '\0' ? "Regular File" : "Symbolic Link",
+					    f->symlink_target == NULL ? "Regular File" : "Symbolic Link",
 					    stat_type_tostring(st.st_mode));
 	}
 
@@ -1779,6 +1781,7 @@ pkg_open_root_fd(struct pkg *pkg)
 pkg_open_root_fd(struct pkg *pkg)
 {
 	const char *path;
+	char rootpath[MAXPATHLEN];
 
 	if (pkg->rootfd != -1)
 		return (EPKG_OK);
@@ -1792,12 +1795,13 @@ pkg_open_root_fd(struct pkg *pkg)
 		return (EPKG_OK);
 	}
 
-	pkg_absolutepath(path, pkg->rootpath, sizeof(pkg->rootpath), false);
+	pkg_absolutepath(path, rootpath, sizeof(rootpath), false);
 
-	if ((pkg->rootfd = openat(ctx.rootfd, pkg->rootpath + 1, O_DIRECTORY)) >= 0 )
+	if ((pkg->rootfd = openat(ctx.rootfd, RELATIVE_PATH(rootpath), O_DIRECTORY)) >= 0 ) {
+		pkg->rootpath = xstrdup(rootpath);
 		return (EPKG_OK);
+	}
 
-	pkg->rootpath[0] = '\0';
 	pkg_emit_errno("open", path);
 
 	return (EPKG_FATAL);
