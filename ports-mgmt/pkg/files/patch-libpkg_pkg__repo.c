--- libpkg/pkg_repo.c.orig	2017-04-03 23:33:23 UTC
+++ libpkg/pkg_repo.c
@@ -1032,6 +1032,10 @@ pkg_repo_load_fingerprint(const char *di
 	obj = ucl_parser_get_object(p);
 	close(fd);
 
+	/* silently return if obj is NULL */
+	if (!obj)
+		return(NULL);
+
 	if (obj->type == UCL_OBJECT)
 		f = pkg_repo_parse_fingerprint(obj);
 
