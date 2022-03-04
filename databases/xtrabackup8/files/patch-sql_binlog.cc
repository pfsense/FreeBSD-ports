--- sql/binlog.cc.orig	2020-08-28 21:02:32 UTC
+++ sql/binlog.cc
@@ -9163,8 +9163,8 @@ void MYSQL_BIN_LOG::report_missing_purged_gtids(
 
   char *missing_gtids = NULL;
   char *slave_executed_gtids = NULL;
-  gtid_missing.to_string(&missing_gtids, NULL);
-  slave_executed_gtid_set->to_string(&slave_executed_gtids, NULL);
+  gtid_missing.to_string(&missing_gtids, false);
+  slave_executed_gtid_set->to_string(&slave_executed_gtids, false);
 
   /*
      Log the information about the missing purged GTIDs to the error log.
@@ -9217,8 +9217,8 @@ void MYSQL_BIN_LOG::report_missing_gtids(
   Gtid_set gtid_missing(slave_executed_gtid_set->get_sid_map());
   gtid_missing.add_gtid_set(slave_executed_gtid_set);
   gtid_missing.remove_gtid_set(previous_gtid_set);
-  gtid_missing.to_string(&missing_gtids, NULL);
-  slave_executed_gtid_set->to_string(&slave_executed_gtids, NULL);
+  gtid_missing.to_string(&missing_gtids, false);
+  slave_executed_gtid_set->to_string(&slave_executed_gtids, false);
 
   String tmp_uuid;
 
