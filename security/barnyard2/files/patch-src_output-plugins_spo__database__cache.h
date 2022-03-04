--- src/output-plugins/spo_database_cache.h.orig	2019-08-07 13:48:17 UTC
+++ src/output-plugins/spo_database_cache.h
@@ -96,9 +96,9 @@
 ** Ref: http://www.postgresql.org/docs/9.1/static/datatype-binary.html
 
 #define PGSQL_SQL_INSERT_SPECIFIC_REFERENCE_SYSTEM "INSERT INTO reference_system (ref_system_name) VALUES (E'%s');"
-#define PGSQL_SQL_SELECT_SPECIFIC_REFERENCE_SYSTEM "SELECT ref_system_id FROM reference_system WHERE ref_system_name = E'%s';"
-#define PGSQL_SQL_INSERT_SPECIFIC_REF  "INSERT INTO reference (ref_system_id,ref_tag) VALUES ('%u',E'%s');"
-#define PGSQL_SQL_SELECT_SPECIFIC_REF  "SELECT ref_id FROM reference WHERE ref_system_id = '%u' AND ref_tag = E'%s';"
+#define PGSQL_SQL_SELECT_SPECIFIC_REFERENCE_SYSTEM "SELECT `ref_system_id` FROM reference_system WHERE ref_system_name = E'%s';"
+#define PGSQL_SQL_INSERT_SPECIFIC_REF  "INSERT INTO reference (`ref_system_id`,ref_tag) VALUES ('%u',E'%s');"
+#define PGSQL_SQL_SELECT_SPECIFIC_REF  "SELECT ref_id FROM reference WHERE `ref_system_id` = '%u' AND ref_tag = E'%s';"
 #define PGSQL_SQL_INSERT_CLASSIFICATION "INSERT INTO sig_class (sig_class_name) VALUES (E'%s');"
 #define PGSQL_SQL_SELECT_SPECIFIC_CLASSIFICATION "SELECT sig_class_id FROM sig_class WHERE sig_class_name = E'%s';"
 #define PGSQL_SQL_INSERT_SIGNATURE "INSERT INTO signature (sig_sid, sig_gid, sig_rev, sig_class_id, sig_priority, sig_name) VALUES ('%u','%u','%u','%u','%u',E'%s');"
@@ -117,9 +117,9 @@
 
 
 #define SQL_INSERT_SPECIFIC_REFERENCE_SYSTEM "INSERT INTO reference_system (ref_system_name) VALUES ('%s');"
-#define SQL_SELECT_SPECIFIC_REFERENCE_SYSTEM "SELECT ref_system_id FROM reference_system WHERE ref_system_name = '%s';"
-#define SQL_INSERT_SPECIFIC_REF  "INSERT INTO reference (ref_system_id,ref_tag) VALUES ('%u','%s');"
-#define SQL_SELECT_SPECIFIC_REF  "SELECT ref_id FROM reference WHERE ref_system_id = '%u' AND ref_tag = '%s';"
+#define SQL_SELECT_SPECIFIC_REFERENCE_SYSTEM "SELECT `ref_system_id` FROM reference_system WHERE ref_system_name = '%s';"
+#define SQL_INSERT_SPECIFIC_REF  "INSERT INTO reference (`ref_system_id`,ref_tag) VALUES ('%u','%s');"
+#define SQL_SELECT_SPECIFIC_REF  "SELECT ref_id FROM reference WHERE `ref_system_id` = '%u' AND ref_tag = '%s';"
 #define SQL_INSERT_CLASSIFICATION "INSERT INTO sig_class (sig_class_name) VALUES ('%s');"
 #define SQL_SELECT_SPECIFIC_CLASSIFICATION "SELECT sig_class_id FROM sig_class WHERE sig_class_name = '%s';"
 #define SQL_INSERT_SIGNATURE "INSERT INTO signature (sig_sid, sig_gid, sig_rev, sig_class_id, sig_priority, sig_name) VALUES ('%u','%u','%u','%u','%u','%s');"
@@ -145,8 +145,8 @@
 #define SQL_SELECT_ALL_SIGREF "SELECT ref_id, sig_id, ref_seq FROM sig_reference ORDER BY sig_id,ref_seq;"
 #define SQL_INSERT_SIGREF "INSERT INTO sig_reference (ref_id,sig_id,ref_seq) VALUES ('%u','%u','%u');"
 #define SQL_SELECT_SPECIFIC_SIGREF "SELECT ref_id FROM sig_reference WHERE (ref_id = '%u') AND (sig_id = '%u') AND (ref_seq='%u');"
-#define SQL_SELECT_ALL_REFERENCE_SYSTEM  "SELECT ref_system_id, ref_system_name FROM reference_system;"
-#define SQL_SELECT_ALL_REF "SELECT ref_id, ref_system_id, ref_tag FROM reference; "
+#define SQL_SELECT_ALL_REFERENCE_SYSTEM  "SELECT `ref_system_id`, ref_system_name FROM reference_system;"
+#define SQL_SELECT_ALL_REF "SELECT ref_id, `ref_system_id`, ref_tag FROM reference; "
 #define SQL_SELECT_ALL_CLASSIFICATION "SELECT sig_class_id, sig_class_name FROM sig_class ORDER BY sig_class_id ASC; "
 #define SQL_SELECT_ALL_SIGNATURE "SELECT sig_id, sig_sid, sig_gid,sig_rev, sig_class_id, sig_priority, sig_name FROM signature;"
 #define SQL_UPDATE_SPECIFIC_SIGNATURE "UPDATE signature SET "		\
