--- libpkg/private/pkg.h.orig	2025-12-23 10:23:55 UTC
+++ libpkg/private/pkg.h
@@ -234,7 +234,7 @@ struct pkg {
 	kvlist_t		 annotations;
 	unsigned			flags;
 	int		rootfd;
-	char		rootpath[MAXPATHLEN];
+	char		*rootpath;
 	charv_t	dir_to_del;
 	pkg_t		 type;
 	struct pkg_repo		*repo;
@@ -359,7 +359,7 @@ struct pkg_config_file {
 } merge_status;
 
 struct pkg_config_file {
-	char path[MAXPATHLEN];
+	char *path;
 	char *content;
 	char *newcontent;
 	merge_status status;
@@ -367,17 +367,17 @@ struct pkg_file {
 };
 
 struct pkg_file {
-	char		 path[MAXPATHLEN];
+	char		*path;
 	int64_t		 size;
 	char		*sum;
-	char		 uname[MAXLOGNAME];
-	char		 gname[MAXLOGNAME];
+	char		*uname;
+	char		*gname;
 	mode_t		 perm;
 	uid_t		 uid;
 	gid_t		 gid;
-	char		 temppath[MAXPATHLEN];
+	char		*temppath;
 	u_long		 fflags;
-	char		 symlink_target[MAXPATHLEN];
+	char		*symlink_target;
 	struct pkg_config_file *config;
 	struct timespec	 time[2];
 	struct pkg_file	*next, *prev;
@@ -385,9 +385,9 @@ struct pkg_dir {
 };
 
 struct pkg_dir {
-	char		 path[MAXPATHLEN];
-	char		 uname[MAXLOGNAME];
-	char		 gname[MAXLOGNAME];
+	char		*path;
+	char		*uname;
+	char		*gname;
 	mode_t		 perm;
 	u_long		 fflags;
 	uid_t		 uid;
@@ -719,6 +719,7 @@ void pkg_file_free(struct pkg_file *);
 
 void pkg_dep_free(struct pkg_dep *);
 void pkg_file_free(struct pkg_file *);
+void pkg_dir_free(struct pkg_dir *);
 void pkg_option_free(struct pkg_option *);
 void pkg_conflict_free(struct pkg_conflict *);
 void pkg_config_file_free(struct pkg_config_file *);
