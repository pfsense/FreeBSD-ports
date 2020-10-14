--- services/mesh.c.orig	2020-10-08 06:24:21 UTC
+++ services/mesh.c
@@ -1196,6 +1196,12 @@ mesh_send_reply(struct mesh_state* m, int rcode, struc
 	/* Copy the client's EDNS for later restore, to make sure the edns
 	 * compare is with the correct edns options. */
 	struct edns_data edns_bak = r->edns;
+	/* briefly set the replylist to null in case the
+	 * meshsendreply calls tcpreqinfo sendreply that
+	 * comm_point_drops because of size, and then the
+	 * null stops the mesh state remove and thus
+	 * reply_list modification and accounting */
+	struct mesh_reply* rlist = m->reply_list;
 	/* examine security status */
 	if(m->s.env->need_to_validate && (!(r->qflags&BIT_CD) ||
 		m->s.env->cfg->ignore_cd) && rep && 
@@ -1236,7 +1242,9 @@ mesh_send_reply(struct mesh_state* m, int rcode, struc
 		sldns_buffer_write_at(r_buffer, 0, &r->qid, sizeof(uint16_t));
 		sldns_buffer_write_at(r_buffer, 12, r->qname,
 			m->s.qinfo.qname_len);
+		m->reply_list = NULL;
 		comm_point_send_reply(&r->query_reply);
+		m->reply_list = rlist;
 	} else if(rcode) {
 		m->s.qinfo.qname = r->qname;
 		m->s.qinfo.local_alias = r->local_alias;
@@ -1251,7 +1259,9 @@ mesh_send_reply(struct mesh_state* m, int rcode, struc
 		}
 		error_encode(r_buffer, rcode, &m->s.qinfo, r->qid,
 			r->qflags, &r->edns);
+		m->reply_list = NULL;
 		comm_point_send_reply(&r->query_reply);
+		m->reply_list = rlist;
 	} else {
 		size_t udp_size = r->edns.udp_size;
 		r->edns.edns_version = EDNS_ADVERTISED_VERSION;
@@ -1277,7 +1287,9 @@ mesh_send_reply(struct mesh_state* m, int rcode, struc
 				&m->s.qinfo, r->qid, r->qflags, &r->edns);
 		}
 		r->edns = edns_bak;
+		m->reply_list = NULL;
 		comm_point_send_reply(&r->query_reply);
+		m->reply_list = rlist;
 	}
 	/* account */
 	log_assert(m->s.env->mesh->num_reply_addrs > 0);
@@ -1365,20 +1377,12 @@ void mesh_query_done(struct mesh_state* mstate)
 			mstate->reply_list = reply_list;
 		} else {
 			struct sldns_buffer* r_buffer = r->query_reply.c->buffer;
-			struct mesh_reply* rlist = mstate->reply_list;
 			if(r->query_reply.c->tcp_req_info) {
 				r_buffer = r->query_reply.c->tcp_req_info->spool_buffer;
 				prev_buffer = NULL;
 			}
-			/* briefly set the replylist to null in case the
-			 * meshsendreply calls tcpreqinfo sendreply that
-			 * comm_point_drops because of size, and then the
-			 * null stops the mesh state remove and thus
-			 * reply_list modification and accounting */
-			mstate->reply_list = NULL;
 			mesh_send_reply(mstate, mstate->s.return_rcode, rep,
 				r, r_buffer, prev, prev_buffer);
-			mstate->reply_list = rlist;
 			if(r->query_reply.c->tcp_req_info) {
 				tcp_req_info_remove_mesh_state(r->query_reply.c->tcp_req_info, mstate);
 				r_buffer = NULL;
@@ -1894,7 +1898,7 @@ mesh_serve_expired_callback(void* arg)
 {
 	struct mesh_state* mstate = (struct mesh_state*) arg;
 	struct module_qstate* qstate = &mstate->s;
-	struct mesh_reply* r, *rlist;
+	struct mesh_reply* r;
 	struct mesh_area* mesh = qstate->env->mesh;
 	struct dns_msg* msg;
 	struct mesh_cb* c;
@@ -1999,15 +2003,8 @@ mesh_serve_expired_callback(void* arg)
 		r_buffer = r->query_reply.c->buffer;
 		if(r->query_reply.c->tcp_req_info)
 			r_buffer = r->query_reply.c->tcp_req_info->spool_buffer;
-		/* briefly set the replylist to null in case the meshsendreply
-		 * calls tcpreqinfo sendreply that comm_point_drops because
-		 * of size, and then the null stops the mesh state remove and
-		 * thus reply_list modification and accounting */
-		rlist = mstate->reply_list;
-		mstate->reply_list = NULL;
 		mesh_send_reply(mstate, LDNS_RCODE_NOERROR, msg->rep,
 			r, r_buffer, prev, prev_buffer);
-		mstate->reply_list = rlist;
 		if(r->query_reply.c->tcp_req_info)
 			tcp_req_info_remove_mesh_state(r->query_reply.c->tcp_req_info, mstate);
 		prev = r;
