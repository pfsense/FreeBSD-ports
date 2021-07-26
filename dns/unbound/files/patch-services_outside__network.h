--- services/outside_network.h.orig	2021-07-26 17:08:07 UTC
+++ services/outside_network.h
@@ -670,12 +670,28 @@ struct waiting_tcp* reuse_tcp_by_id_find(struct reuse_
 /** insert element in tree by id */
 void reuse_tree_by_id_insert(struct reuse_tcp* reuse, struct waiting_tcp* w);
 
+/** insert element in tcp_reuse tree and LRU list */
+int reuse_tcp_insert(struct outside_network* outnet,
+	struct pending_tcp* pend_tcp);
+
+/** touch the LRU of the element */
+void reuse_tcp_lru_touch(struct outside_network* outnet,
+	struct reuse_tcp* reuse);
+
+/** remove element from tree and LRU list */
+void reuse_tcp_remove_tree_list(struct outside_network* outnet,
+	struct reuse_tcp* reuse);
+
+/** snip the last reuse_tcp element off of the LRU list if any */
+struct reuse_tcp* reuse_tcp_lru_snip(struct outside_network* outnet);
+
 /** delete readwait waiting_tcp elements, deletes the elements in the list */
 void reuse_del_readwait(rbtree_type* tree_by_id);
 
 /** get TCP file descriptor for address, returns -1 on failure,
  * tcp_mss is 0 or maxseg size to set for TCP packets. */
-int outnet_get_tcp_fd(struct sockaddr_storage* addr, socklen_t addrlen, int tcp_mss, int dscp);
+int outnet_get_tcp_fd(struct sockaddr_storage* addr, socklen_t addrlen,
+	int tcp_mss, int dscp);
 
 /**
  * Create udp commpoint suitable for sending packets to the destination.
