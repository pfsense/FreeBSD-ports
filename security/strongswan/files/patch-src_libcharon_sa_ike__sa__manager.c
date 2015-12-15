--- src/libcharon/sa/ike_sa_manager.c.orig	2015-12-15 16:13:38 UTC
+++ src/libcharon/sa/ike_sa_manager.c
@@ -727,7 +727,8 @@ static bool wait_for_entry(private_ike_s
 		/* we are not allowed to get this */
 		return FALSE;
 	}
-	while (entry->checked_out && !entry->driveout_waiting_threads)
+	/* Must avoid waiting on an SA created by checkout_by_message() but not checked in yet */
+	while (entry->checked_out && !entry->driveout_waiting_threads && entry->processing == -1)
 	{
 		/* so wait until we can get it for us.
 		 * we register us as waiting. */
@@ -736,7 +737,7 @@ static bool wait_for_entry(private_ike_s
 		entry->waiting_threads--;
 	}
 	/* hm, a deletion request forbids us to get this SA, get next one */
-	if (entry->driveout_waiting_threads)
+	if (entry->driveout_waiting_threads || entry->processing != -1)
 	{
 		/* we must signal here, others may be waiting on it, too */
 		entry->condvar->signal(entry->condvar);
