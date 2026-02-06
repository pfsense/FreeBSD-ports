--- libpkg/pkg_printf.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/pkg_printf.c
@@ -1257,7 +1257,7 @@ format_file_symlink_target(xstring *buf, const void *d
 format_file_symlink_target(xstring *buf, const void *data, struct percent_esc *p)
 {
 	const struct pkg_file *file = data;
-	return (string_val(buf, file->symlink_target, p));
+	return (string_val(buf, file->symlink_target ? file->symlink_target : "", p));
 }
 
 /*
