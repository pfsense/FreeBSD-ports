--- libpkg/pkg_attributes.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkg_attributes.c
@@ -63,10 +63,23 @@ pkg_file_free(struct pkg_file *file)
 void
 pkg_file_free(struct pkg_file *file)
 {
+	free(file->path);
+	free(file->uname);
+	free(file->gname);
+	free(file->symlink_target);
 	free(file->sum);
 	free(file);
 }
 
+void
+pkg_dir_free(struct pkg_dir *dir)
+{
+	free(dir->path);
+	free(dir->uname);
+	free(dir->gname);
+	free(dir);
+}
+
 /*
  * Script
  */
@@ -120,6 +133,7 @@ pkg_config_file_free(struct pkg_config_file *c)
 	if (c == NULL)
 		return;
 
+	free(c->path);
 	free(c->content);
 	free(c);
 }
