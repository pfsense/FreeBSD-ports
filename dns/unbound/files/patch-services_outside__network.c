--- services/outside_network.c.orig	2021-07-26 17:08:15 UTC
+++ services/outside_network.c
@@ -90,10 +90,6 @@ static int randomize_and_send_udp(struct pending* pend
 static void waiting_list_remove(struct outside_network* outnet,
 	struct waiting_tcp* w);
 
-/** remove reused element from tree and lru list */
-static void reuse_tcp_remove_tree_list(struct outside_network* outnet,
-	struct reuse_tcp* reuse);
-
 int 
 pending_cmp(const void* key1, const void* key2)
 {
@@ -356,6 +352,8 @@ static struct waiting_tcp* reuse_write_wait_pop(struct
 		w->write_wait_next->write_wait_prev = NULL;
 	else	reuse->write_wait_last = NULL;
 	w->write_wait_queued = 0;
+	w->write_wait_next = NULL;
+	w->write_wait_prev = NULL;
 	return w;
 }
 
@@ -363,6 +361,8 @@ static struct waiting_tcp* reuse_write_wait_pop(struct
 static void reuse_write_wait_remove(struct reuse_tcp* reuse,
 	struct waiting_tcp* w)
 {
+	log_assert(w);
+	log_assert(w->write_wait_queued);
 	if(!w)
 		return;
 	if(!w->write_wait_queued)
@@ -370,10 +370,16 @@ static void reuse_write_wait_remove(struct reuse_tcp* 
 	if(w->write_wait_prev)
 		w->write_wait_prev->write_wait_next = w->write_wait_next;
 	else	reuse->write_wait_first = w->write_wait_next;
+	log_assert(!w->write_wait_prev ||
+		w->write_wait_prev->write_wait_next != w->write_wait_prev);
 	if(w->write_wait_next)
 		w->write_wait_next->write_wait_prev = w->write_wait_prev;
 	else	reuse->write_wait_last = w->write_wait_prev;
+	log_assert(!w->write_wait_next
+		|| w->write_wait_next->write_wait_prev != w->write_wait_next);
 	w->write_wait_queued = 0;
+	w->write_wait_next = NULL;
+	w->write_wait_prev = NULL;
 }
 
 /** push the element after the last on the writewait list */
@@ -384,6 +390,8 @@ static void reuse_write_wait_push_back(struct reuse_tc
 	log_assert(!w->write_wait_queued);
 	if(reuse->write_wait_last) {
 		reuse->write_wait_last->write_wait_next = w;
+		log_assert(reuse->write_wait_last->write_wait_next !=
+			reuse->write_wait_last);
 		w->write_wait_prev = reuse->write_wait_last;
 	} else {
 		reuse->write_wait_first = w;
@@ -424,34 +432,45 @@ tree_by_id_get_id(rbnode_type* node)
 }
 
 /** insert into reuse tcp tree and LRU, false on failure (duplicate) */
-static int
+int
 reuse_tcp_insert(struct outside_network* outnet, struct pending_tcp* pend_tcp)
 {
 	log_reuse_tcp(VERB_CLIENT, "reuse_tcp_insert", &pend_tcp->reuse);
 	if(pend_tcp->reuse.item_on_lru_list) {
 		if(!pend_tcp->reuse.node.key)
-			log_err("internal error: reuse_tcp_insert: on lru list without key");
+			log_err("internal error: reuse_tcp_insert: "
+				"in lru list without key");
 		return 1;
 	}
 	pend_tcp->reuse.node.key = &pend_tcp->reuse;
 	pend_tcp->reuse.pending = pend_tcp;
 	if(!rbtree_insert(&outnet->tcp_reuse, &pend_tcp->reuse.node)) {
-		/* this is a duplicate connection, close this one */
-		verbose(VERB_CLIENT, "reuse_tcp_insert: duplicate connection");
-		pend_tcp->reuse.node.key = NULL;
-		return 0;
+		/* We are not in the LRU list but we are already in the
+		 * tcp_reuse tree, strange.
+		 * Continue to add ourselves to the LRU list. */
+		log_err("internal error: reuse_tcp_insert: in lru list but "
+			"not in the tree");
 	}
 	/* insert into LRU, first is newest */
 	pend_tcp->reuse.lru_prev = NULL;
 	if(outnet->tcp_reuse_first) {
 		pend_tcp->reuse.lru_next = outnet->tcp_reuse_first;
+		log_assert(pend_tcp->reuse.lru_next != &pend_tcp->reuse);
 		outnet->tcp_reuse_first->lru_prev = &pend_tcp->reuse;
+		log_assert(outnet->tcp_reuse_first->lru_prev !=
+			outnet->tcp_reuse_first);
 	} else {
 		pend_tcp->reuse.lru_next = NULL;
 		outnet->tcp_reuse_last = &pend_tcp->reuse;
 	}
 	outnet->tcp_reuse_first = &pend_tcp->reuse;
 	pend_tcp->reuse.item_on_lru_list = 1;
+	log_assert((!outnet->tcp_reuse_first && !outnet->tcp_reuse_last) ||
+		(outnet->tcp_reuse_first && outnet->tcp_reuse_last));
+	log_assert(outnet->tcp_reuse_first != outnet->tcp_reuse_first->lru_next &&
+		outnet->tcp_reuse_first != outnet->tcp_reuse_first->lru_prev);
+	log_assert(outnet->tcp_reuse_last != outnet->tcp_reuse_last->lru_next &&
+		outnet->tcp_reuse_last != outnet->tcp_reuse_last->lru_prev);
 	return 1;
 }
 
@@ -689,53 +708,140 @@ outnet_tcp_take_into_use(struct waiting_tcp* w)
 /** Touch the lru of a reuse_tcp element, it is in use.
  * This moves it to the front of the list, where it is not likely to
  * be closed.  Items at the back of the list are closed to make space. */
-static void
+void
 reuse_tcp_lru_touch(struct outside_network* outnet, struct reuse_tcp* reuse)
 {
 	if(!reuse->item_on_lru_list) {
 		log_err("internal error: we need to touch the lru_list but item not in list");
 		return; /* not on the list, no lru to modify */
 	}
+	log_assert(reuse->lru_prev ||
+		(!reuse->lru_prev && outnet->tcp_reuse_first == reuse));
 	if(!reuse->lru_prev)
 		return; /* already first in the list */
 	/* remove at current position */
 	/* since it is not first, there is a previous element */
 	reuse->lru_prev->lru_next = reuse->lru_next;
+	log_assert(reuse->lru_prev->lru_next != reuse->lru_prev);
 	if(reuse->lru_next)
 		reuse->lru_next->lru_prev = reuse->lru_prev;
 	else	outnet->tcp_reuse_last = reuse->lru_prev;
+	log_assert(!reuse->lru_next || reuse->lru_next->lru_prev != reuse->lru_next);
+	log_assert(outnet->tcp_reuse_last != outnet->tcp_reuse_last->lru_next &&
+		outnet->tcp_reuse_last != outnet->tcp_reuse_last->lru_prev);
 	/* insert at the front */
 	reuse->lru_prev = NULL;
 	reuse->lru_next = outnet->tcp_reuse_first;
+	if(outnet->tcp_reuse_first) {
+		outnet->tcp_reuse_first->lru_prev = reuse;
+	}
+	log_assert(reuse->lru_next != reuse);
 	/* since it is not first, it is not the only element and
 	 * lru_next is thus not NULL and thus reuse is now not the last in
 	 * the list, so outnet->tcp_reuse_last does not need to be modified */
 	outnet->tcp_reuse_first = reuse;
+	log_assert(outnet->tcp_reuse_first != outnet->tcp_reuse_first->lru_next &&
+		outnet->tcp_reuse_first != outnet->tcp_reuse_first->lru_prev);
+	log_assert((!outnet->tcp_reuse_first && !outnet->tcp_reuse_last) ||
+		(outnet->tcp_reuse_first && outnet->tcp_reuse_last));
 }
 
+/** Snip the last reuse_tcp element off of the LRU list */
+struct reuse_tcp*
+reuse_tcp_lru_snip(struct outside_network* outnet)
+{
+	struct reuse_tcp* reuse = outnet->tcp_reuse_last;
+	if(!reuse) return NULL;
+	/* snip off of LRU */
+	log_assert(reuse->lru_next == NULL);
+	if(reuse->lru_prev) {
+		outnet->tcp_reuse_last = reuse->lru_prev;
+		reuse->lru_prev->lru_next = NULL;
+	} else {
+		outnet->tcp_reuse_last = NULL;
+		outnet->tcp_reuse_first = NULL;
+	}
+	log_assert((!outnet->tcp_reuse_first && !outnet->tcp_reuse_last) ||
+		(outnet->tcp_reuse_first && outnet->tcp_reuse_last));
+	reuse->item_on_lru_list = 0;
+	reuse->lru_next = NULL;
+	reuse->lru_prev = NULL;
+	return reuse;
+}
+
 /** call callback on waiting_tcp, if not NULL */
 static void
 waiting_tcp_callback(struct waiting_tcp* w, struct comm_point* c, int error,
 	struct comm_reply* reply_info)
 {
-	if(w->cb) {
+	if(w && w->cb) {
 		fptr_ok(fptr_whitelist_pending_tcp(w->cb));
 		(void)(*w->cb)(c, w->cb_arg, error, reply_info);
 	}
 }
 
+/** add waiting_tcp element to the outnet tcp waiting list */
+static void
+outnet_add_tcp_waiting(struct outside_network* outnet, struct waiting_tcp* w)
+{
+	struct timeval tv;
+	log_assert(!w->on_tcp_waiting_list);
+	if(w->on_tcp_waiting_list)
+		return;
+	w->next_waiting = NULL;
+	if(outnet->tcp_wait_last)
+		outnet->tcp_wait_last->next_waiting = w;
+	else	outnet->tcp_wait_first = w;
+	outnet->tcp_wait_last = w;
+	w->on_tcp_waiting_list = 1;
+#ifndef S_SPLINT_S
+	tv.tv_sec = w->timeout/1000;
+	tv.tv_usec = (w->timeout%1000)*1000;
+#endif
+	comm_timer_set(w->timer, &tv);
+}
+
+/** add waiting_tcp element as first to the outnet tcp waiting list */
+static void
+outnet_add_tcp_waiting_first(struct outside_network* outnet,
+	struct waiting_tcp* w, int reset_timer)
+{
+	struct timeval tv;
+	log_assert(!w->on_tcp_waiting_list);
+	if(w->on_tcp_waiting_list)
+		return;
+	w->next_waiting = outnet->tcp_wait_first;
+	if(!outnet->tcp_wait_last)
+		outnet->tcp_wait_last = w;
+	outnet->tcp_wait_first = w;
+	w->on_tcp_waiting_list = 1;
+	if(reset_timer) {
+#ifndef S_SPLINT_S
+		tv.tv_sec = w->timeout/1000;
+		tv.tv_usec = (w->timeout%1000)*1000;
+#endif
+		comm_timer_set(w->timer, &tv);
+	}
+	log_assert(
+		(!outnet->tcp_reuse_first && !outnet->tcp_reuse_last) ||
+		(outnet->tcp_reuse_first && outnet->tcp_reuse_last));
+}
+
 /** see if buffers can be used to service TCP queries */
 static void
 use_free_buffer(struct outside_network* outnet)
 {
 	struct waiting_tcp* w;
-	while(outnet->tcp_free && outnet->tcp_wait_first 
-		&& !outnet->want_to_quit) {
+	while(outnet->tcp_wait_first && !outnet->want_to_quit) {
 		struct reuse_tcp* reuse = NULL;
 		w = outnet->tcp_wait_first;
+		log_assert(w->on_tcp_waiting_list);
 		outnet->tcp_wait_first = w->next_waiting;
 		if(outnet->tcp_wait_last == w)
 			outnet->tcp_wait_last = NULL;
+		log_assert(
+			(!outnet->tcp_reuse_first && !outnet->tcp_reuse_last) ||
+			(outnet->tcp_reuse_first && outnet->tcp_reuse_last));
 		w->on_tcp_waiting_list = 0;
 		reuse = reuse_tcp_find(outnet, &w->addr, w->addrlen,
 			w->ssl_upstream);
@@ -758,7 +864,7 @@ use_free_buffer(struct outside_network* outnet)
 					reuse->pending->c->fd, reuse->pending,
 					w);
 			}
-		} else {
+		} else if(outnet->tcp_free) {
 			struct pending_tcp* pend = w->outnet->tcp_free;
 			rbtree_init(&pend->reuse.tree_by_id, reuse_id_cmp);
 			pend->reuse.pending = pend;
@@ -773,26 +879,6 @@ use_free_buffer(struct outside_network* outnet)
 	}
 }
 
-/** add waiting_tcp element to the outnet tcp waiting list */
-static void
-outnet_add_tcp_waiting(struct outside_network* outnet, struct waiting_tcp* w)
-{
-	struct timeval tv;
-	if(w->on_tcp_waiting_list)
-		return;
-	w->next_waiting = NULL;
-	if(outnet->tcp_wait_last)
-		outnet->tcp_wait_last->next_waiting = w;
-	else	outnet->tcp_wait_first = w;
-	outnet->tcp_wait_last = w;
-	w->on_tcp_waiting_list = 1;
-#ifndef S_SPLINT_S
-	tv.tv_sec = w->timeout/1000;
-	tv.tv_usec = (w->timeout%1000)*1000;
-#endif
-	comm_timer_set(w->timer, &tv);
-}
-
 /** delete element from tree by id */
 static void
 reuse_tree_by_id_delete(struct reuse_tcp* reuse, struct waiting_tcp* w)
@@ -857,7 +943,7 @@ reuse_move_writewait_away(struct outside_network* outn
 }
 
 /** remove reused element from tree and lru list */
-static void
+void
 reuse_tcp_remove_tree_list(struct outside_network* outnet,
 	struct reuse_tcp* reuse)
 {
@@ -866,6 +952,9 @@ reuse_tcp_remove_tree_list(struct outside_network* out
 		/* delete it from reuse tree */
 		(void)rbtree_delete(&outnet->tcp_reuse, reuse);
 		reuse->node.key = NULL;
+		/* defend against loops on broken tree by zeroing the
+		 * rbnode structure */
+		memset(&reuse->node, 0, sizeof(reuse->node));
 	}
 	/* delete from reuse list */
 	if(reuse->item_on_lru_list) {
@@ -874,21 +963,38 @@ reuse_tcp_remove_tree_list(struct outside_network* out
 			 * and thus have a pending pointer to the struct */
 			log_assert(reuse->lru_prev->pending);
 			reuse->lru_prev->lru_next = reuse->lru_next;
+			log_assert(reuse->lru_prev->lru_next != reuse->lru_prev);
 		} else {
 			log_assert(!reuse->lru_next || reuse->lru_next->pending);
 			outnet->tcp_reuse_first = reuse->lru_next;
+			log_assert(!outnet->tcp_reuse_first ||
+				(outnet->tcp_reuse_first !=
+				 outnet->tcp_reuse_first->lru_next &&
+				 outnet->tcp_reuse_first !=
+				 outnet->tcp_reuse_first->lru_prev));
 		}
 		if(reuse->lru_next) {
 			/* assert that members of the lru list are waiting
 			 * and thus have a pending pointer to the struct */
 			log_assert(reuse->lru_next->pending);
 			reuse->lru_next->lru_prev = reuse->lru_prev;
+			log_assert(reuse->lru_next->lru_prev != reuse->lru_next);
 		} else {
 			log_assert(!reuse->lru_prev || reuse->lru_prev->pending);
 			outnet->tcp_reuse_last = reuse->lru_prev;
+			log_assert(!outnet->tcp_reuse_last ||
+				(outnet->tcp_reuse_last !=
+				 outnet->tcp_reuse_last->lru_next &&
+				 outnet->tcp_reuse_last !=
+				 outnet->tcp_reuse_last->lru_prev));
 		}
+		log_assert((!outnet->tcp_reuse_first && !outnet->tcp_reuse_last) ||
+			(outnet->tcp_reuse_first && outnet->tcp_reuse_last));
 		reuse->item_on_lru_list = 0;
+		reuse->lru_next = NULL;
+		reuse->lru_prev = NULL;
 	}
+	reuse->pending = NULL;
 }
 
 /** helper function that deletes an element from the tree of readwait
@@ -915,8 +1021,12 @@ decommission_pending_tcp(struct outside_network* outne
 	struct pending_tcp* pend)
 {
 	verbose(VERB_CLIENT, "decommission_pending_tcp");
-	pend->next_free = outnet->tcp_free;
-	outnet->tcp_free = pend;
+	/* A certain code path can lead here twice for the same pending_tcp
+	 * creating a loop in the free pending_tcp list. */
+	if(outnet->tcp_free != pend) {
+		pend->next_free = outnet->tcp_free;
+		outnet->tcp_free = pend;
+	}
 	if(pend->reuse.node.key) {
 		/* needs unlink from the reuse tree to get deleted */
 		reuse_tcp_remove_tree_list(outnet, &pend->reuse);
@@ -1002,6 +1112,7 @@ outnet_tcp_cb(struct comm_point* c, void* arg, int err
 	struct pending_tcp* pend = (struct pending_tcp*)arg;
 	struct outside_network* outnet = pend->reuse.outnet;
 	struct waiting_tcp* w = NULL;
+	log_assert(pend->reuse.item_on_lru_list && pend->reuse.node.key);
 	verbose(VERB_ALGO, "outnettcp cb");
 	if(error == NETEVENT_TIMEOUT) {
 		if(pend->c->tcp_write_and_read) {
@@ -1609,22 +1720,19 @@ outside_network_delete(struct outside_network* outnet)
 		size_t i;
 		for(i=0; i<outnet->num_tcp; i++)
 			if(outnet->tcp_conns[i]) {
-				if(outnet->tcp_conns[i]->query &&
-					!outnet->tcp_conns[i]->query->
-					on_tcp_waiting_list) {
+				struct pending_tcp* pend;
+				pend = outnet->tcp_conns[i];
+				if(pend->reuse.item_on_lru_list) {
 					/* delete waiting_tcp elements that
 					 * the tcp conn is working on */
-					struct pending_tcp* pend =
-						(struct pending_tcp*)outnet->
-						tcp_conns[i]->query->
-						next_waiting;
 					decommission_pending_tcp(outnet, pend);
 				}
 				comm_point_delete(outnet->tcp_conns[i]->c);
-				waiting_tcp_delete(outnet->tcp_conns[i]->query);
 				free(outnet->tcp_conns[i]);
+				outnet->tcp_conns[i] = NULL;
 			}
 		free(outnet->tcp_conns);
+		outnet->tcp_conns = NULL;
 	}
 	if(outnet->tcp_wait_first) {
 		struct waiting_tcp* p = outnet->tcp_wait_first, *np;
@@ -2011,24 +2119,12 @@ outnet_tcptimer(void* arg)
 static void
 reuse_tcp_close_oldest(struct outside_network* outnet)
 {
-	struct pending_tcp* pend;
+	struct reuse_tcp* reuse;
 	verbose(VERB_CLIENT, "reuse_tcp_close_oldest");
-	if(!outnet->tcp_reuse_last) return;
-	pend = outnet->tcp_reuse_last->pending;
-
-	/* snip off of LRU */
-	log_assert(pend->reuse.lru_next == NULL);
-	if(pend->reuse.lru_prev) {
-		outnet->tcp_reuse_last = pend->reuse.lru_prev;
-		pend->reuse.lru_prev->lru_next = NULL;
-	} else {
-		outnet->tcp_reuse_last = NULL;
-		outnet->tcp_reuse_first = NULL;
-	}
-	pend->reuse.item_on_lru_list = 0;
-
+	reuse = reuse_tcp_lru_snip(outnet);
+	if(!reuse) return;
 	/* free up */
-	reuse_cb_and_decommission(outnet, pend, NETEVENT_CLOSED);
+	reuse_cb_and_decommission(outnet, reuse->pending, NETEVENT_CLOSED);
 }
 
 /** find spare ID value for reuse tcp stream.  That is random and also does
@@ -2126,6 +2222,7 @@ pending_tcp_query(struct serviced_query* sq, sldns_buf
 		reuse_tcp_lru_touch(sq->outnet, reuse);
 	}
 
+	log_assert(!reuse || (reuse && pend));
 	/* if !pend but we have reuse streams, close a reuse stream
 	 * to be able to open a new one to this target, no use waiting
 	 * to reuse a file descriptor while another query needs to use
@@ -2133,6 +2230,7 @@ pending_tcp_query(struct serviced_query* sq, sldns_buf
 	if(!pend) {
 		reuse_tcp_close_oldest(sq->outnet);
 		pend = sq->outnet->tcp_free;
+		log_assert(!reuse || (pend == reuse->pending));
 	}
 
 	/* allocate space to store query */
@@ -2170,6 +2268,7 @@ pending_tcp_query(struct serviced_query* sq, sldns_buf
 	if(pend) {
 		/* we have a buffer available right now */
 		if(reuse) {
+			log_assert(reuse == &pend->reuse);
 			/* reuse existing fd, write query and continue */
 			/* store query in tree by id */
 			verbose(VERB_CLIENT, "pending_tcp_query: reuse, store");
@@ -2348,6 +2447,9 @@ waiting_list_remove(struct outside_network* outnet, st
 		prev = p;
 		p = p->next_waiting;
 	}
+	/* waiting_list_remove is currently called only with items that are
+	 * already in the waiting list. */
+	log_assert(0);
 }
 
 /** reuse tcp stream, remove serviced query from stream,
