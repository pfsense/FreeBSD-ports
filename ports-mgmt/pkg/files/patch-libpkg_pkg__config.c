--- libpkg/pkg_config.c.orig	2022-08-03 07:37:06 UTC
+++ libpkg/pkg_config.c
@@ -464,7 +464,7 @@ static struct config_entry c[] = {
 	{
 		PKG_ARRAY,
 		"AUDIT_IGNORE_REGEX",
-		"NULL",
+		NULL,
 		"List of regex to ignore while autiditing for vulnerabilities",
 	},
 	{
@@ -488,13 +488,13 @@ static struct config_entry c[] = {
 	{
 		PKG_ARRAY,
 		"FILES_IGNORE_GLOB",
-		"NULL",
+		NULL,
 		"patterns of files to not extract from the package",
 	},
 	{
 		PKG_ARRAY,
 		"FILES_IGNORE_REGEX",
-		"NULL",
+		NULL,
 		"patterns of files to not extract from the package",
 	},
 };
