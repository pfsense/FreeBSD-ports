--- libpkg/pkg_add.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkg_add.c
@@ -465,8 +465,8 @@ do_extract_dir(struct pkg_add_context* context, struct
 	d->perm = aest->st_mode;
 	d->uid = get_uid_from_uname(archive_entry_uname(ae));
 	d->gid = get_gid_from_gname(archive_entry_gname(ae));
-	strlcpy(d->uname, archive_entry_uname(ae), sizeof(d->uname));
-	strlcpy(d->gname, archive_entry_gname(ae), sizeof(d->gname));
+	d->uname = xstrdup(archive_entry_uname(ae));
+	d->gname = xstrdup(archive_entry_uname(ae));
 	fill_timespec_buf(aest, d->time);
 	archive_entry_fflags(ae, &d->fflags, &clear);
 
@@ -504,8 +504,11 @@ create_symlinks(struct pkg_add_context *context, struc
 	bool tried_mkdir = false;
 
 	tmpdir = get_tempdir(context, f->path, tempdirs);
-	if (tmpdir == NULL && errno == 0)
-		hidden_tempfile(f->temppath, sizeof(f->temppath), f->path);
+	if (tmpdir == NULL && errno == 0) {
+		char temppath[MAXPATHLEN] = { 0 };
+		hidden_tempfile(temppath, sizeof(temppath), f->path);
+		f->temppath = xstrdup(temppath);
+	}
 	if (tmpdir == NULL) {
 		fd = context->rootfd;
 		path = f->temppath;
@@ -559,8 +562,8 @@ do_extract_symlink(struct pkg_add_context *context, st
 	archive_entry_fflags(ae, &f->fflags, &clear);
 	f->uid = get_uid_from_uname(archive_entry_uname(ae));
 	f->gid = get_gid_from_gname(archive_entry_gname(ae));
-	strlcpy(f->uname, archive_entry_uname(ae), sizeof(f->uname));
-	strlcpy(f->gname, archive_entry_gname(ae), sizeof(f->gname));
+	f->uname = xstrdup(archive_entry_uname(ae));
+	f->gname = xstrdup(archive_entry_gname(ae));
 	f->perm = aest->st_mode;
 	fill_timespec_buf(aest, f->time);
 	archive_entry_fflags(ae, &f->fflags, &clear);
@@ -586,8 +589,11 @@ create_hardlink(struct pkg_add_context *context, struc
 	struct tempdir *tmphdir = NULL;
 
 	tmpdir = get_tempdir(context, f->path, tempdirs);
-	if (tmpdir == NULL && errno == 0)
-		hidden_tempfile(f->temppath, sizeof(f->temppath), f->path);
+	if (tmpdir == NULL && errno == 0) {
+		char temppath[MAXPATHLEN] = { 0 };
+		hidden_tempfile(temppath, sizeof(temppath), f->path);
+		f->temppath = xstrdup(temppath);
+	}
 	if (tmpdir != NULL) {
 		fd = tmpdir->fd;
 	} else {
@@ -600,7 +606,7 @@ create_hardlink(struct pkg_add_context *context, struc
 		    " hardlinked to %s", f->path, path);
 		return (EPKG_FATAL);
 	}
-	if (fh->temppath[0] == '\0') {
+	if (fh->temppath != NULL) {
 		vec_foreach(*tempdirs, i) {
 			if (strncmp(tempdirs->d[i]->name, fh->path, tempdirs->d[i]->len) == 0 &&
 			    fh->path[tempdirs->d[i]->len] == '/' ) {
@@ -706,8 +712,11 @@ create_regfile(struct pkg_add_context *context, struct
 	struct tempdir *tmpdir = NULL;
 
 	tmpdir = get_tempdir(context, f->path, tempdirs);
-	if (tmpdir == NULL && errno == 0)
-		hidden_tempfile(f->temppath, sizeof(f->temppath), f->path);
+	if (tmpdir == NULL && errno == 0) {
+		char temppath[MAXPATHLEN] = { 0 };
+		hidden_tempfile(temppath, sizeof(temppath), f->path);
+		f->temppath = xstrdup(temppath);
+	}
 
 	if (tmpdir != NULL) {
 		fd = open_tempfile(tmpdir->fd, f->path + tmpdir->len, f->perm);
@@ -807,8 +816,8 @@ do_extract_regfile(struct pkg_add_context *context, st
 	f->perm = aest->st_mode;
 	f->uid = get_uid_from_uname(archive_entry_uname(ae));
 	f->gid = get_gid_from_gname(archive_entry_gname(ae));
-	strlcpy(f->uname, archive_entry_uname(ae), sizeof(f->uname));
-	strlcpy(f->gname, archive_entry_gname(ae), sizeof(f->gname));
+	f->uname = xstrdup(archive_entry_uname(ae));
+	f->gname = xstrdup(archive_entry_gname(ae));
 	fill_timespec_buf(aest, f->time);
 	archive_entry_fflags(ae, &f->fflags, &clear);
 
@@ -985,7 +994,7 @@ pkg_extract_finalize(struct pkg *pkg, tempdirs_t *temp
 		    pkg_config_get("FILES_IGNORE_REGEX")))
 			continue;
 		append_touched_file(f->path);
-		if (*f->temppath == '\0')
+		if (f->temppath == NULL)
 			continue;
 		fto = f->path;
 		if (f->config && f->config->status == MERGE_FAILED &&
@@ -1282,7 +1291,7 @@ pkg_rollback_pkg(struct pkg *p)
 		    pkg_config_get("FILES_IGNORE_GLOB"),
 		    pkg_config_get("FILES_IGNORE_REGEX")))
 			continue;
-		if (*f->temppath != '\0') {
+		if (f->temppath  != NULL) {
 			unlinkat(p->rootfd, f->temppath, 0);
 		}
 	}
@@ -1651,7 +1660,7 @@ pkg_add_fromdir(struct pkg *pkg, const char *src, stru
 		}
 		if (d->perm == 0)
 			d->perm = st.st_mode & ~S_IFMT;
-		if (d->uname[0] != '\0') {
+		if (d->uname != NULL) {
 			err = getpwnam_r(d->uname, &pwent, buffer,
 			    sizeof(buffer), &pw);
 			if (err != 0) {
@@ -1663,7 +1672,7 @@ pkg_add_fromdir(struct pkg *pkg, const char *src, stru
 		} else {
 			d->uid = install_as_user ? st.st_uid : 0;
 		}
-		if (d->gname[0] != '\0') {
+		if (d->gname != NULL) {
 			err = getgrnam_r(d->gname, &grent, buffer,
 			    sizeof(buffer), &gr);
 			if (err != 0) {
@@ -1707,7 +1716,7 @@ pkg_add_fromdir(struct pkg *pkg, const char *src, stru
 			close(fromfd);
 			pkg_fatal_errno("%s%s", src, f->path);
 		}
-		if (f->uname[0] != '\0') {
+		if (f->uname != NULL) {
 			err = getpwnam_r(f->uname, &pwent, buffer,
 			    sizeof(buffer), &pw);
 			if (err != 0) {
@@ -1720,7 +1729,7 @@ pkg_add_fromdir(struct pkg *pkg, const char *src, stru
 			f->uid = install_as_user ? st.st_uid : 0;
 		}
 
-		if (f->gname[0] != '\0') {
+		if (f->gname != NULL) {
 			err = getgrnam_r(f->gname, &grent, buffer,
 			    sizeof(buffer), &gr);
 			if (err != 0) {
