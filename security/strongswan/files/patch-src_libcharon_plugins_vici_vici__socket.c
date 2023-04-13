--- src/libcharon/plugins/vici/vici_socket.c.orig	2022-06-29 13:00:45 UTC
+++ src/libcharon/plugins/vici/vici_socket.c
@@ -127,6 +127,8 @@ typedef struct {
 	int readers;
 	/** any users writing over this connection? */
 	int writers;
+	/** any users using this connection at all? */
+	int users;
 	/** condvar to wait for usage  */
 	condvar_t *cond;
 } entry_t;
@@ -211,6 +213,7 @@ static entry_t* find_entry(private_vici_socket_t *this
 			{
 				entry->writers++;
 			}
+			entry->users++;
 			found = entry;
 			break;
 		}
@@ -240,7 +243,7 @@ static entry_t* remove_entry(private_vici_socket_t *th
 			if (entry->id == id)
 			{
 				candidate = TRUE;
-				if (entry->readers || entry->writers)
+				if (entry->readers || entry->writers || entry->users)
 				{
 					entry->cond->wait(entry->cond, this->mutex);
 					break;
@@ -273,6 +276,7 @@ static void put_entry(private_vici_socket_t *this, ent
 	{
 		entry->writers--;
 	}
+	entry->users--;
 	entry->cond->signal(entry->cond);
 	this->mutex->unlock(this->mutex);
 }
@@ -583,6 +587,7 @@ CALLBACK(on_accept, bool,
 		.queue = array_create(sizeof(chunk_t), 0),
 		.cond = condvar_create(CONDVAR_TYPE_DEFAULT),
 		.readers = 1,
+		.users = 1,
 	);
 
 	this->mutex->lock(this->mutex);
@@ -606,11 +611,13 @@ CALLBACK(enable_writer, job_requeue_t,
 {
 	entry_t *entry;
 
-	entry = find_entry(sel->this, NULL, sel->id, FALSE, TRUE);
+	/* we don't modify the in- or outbound queue, so don't lock the entry in
+	 * reader or writer mode */
+	entry = find_entry(sel->this, NULL, sel->id, FALSE, FALSE);
 	if (entry)
 	{
 		entry->stream->on_write(entry->stream, on_write, sel->this);
-		put_entry(sel->this, entry, FALSE, TRUE);
+		put_entry(sel->this, entry, FALSE, FALSE);
 	}
 	return JOB_REQUEUE_NONE;
 }
