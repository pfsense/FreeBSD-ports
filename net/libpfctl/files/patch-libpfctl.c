--- libpfctl.c.orig	2023-11-17 12:21:14 UTC
+++ libpfctl.c
@@ -1014,7 +1014,8 @@ snl_add_msg_attr_pf_rule(struct snl_writer *nw, uint32
 	snl_add_msg_attr_string(nw, PF_RT_TAGNAME, r->tagname);
 	snl_add_msg_attr_string(nw, PF_RT_MATCH_TAGNAME, r->match_tagname);
 	snl_add_msg_attr_string(nw, PF_RT_OVERLOAD_TBLNAME, r->overload_tblname);
-	snl_add_msg_attr_rpool(nw, PF_RT_RPOOL, &r->rpool);
+	snl_add_msg_attr_rpool(nw, PF_RT_RPOOL_RDR, &r->rdr);
+	snl_add_msg_attr_rpool(nw, PF_RT_RPOOL_NAT, &r->nat);
 	snl_add_msg_attr_u32(nw, PF_RT_OS_FINGERPRINT, r->os_fingerprint);
 	snl_add_msg_attr_u32(nw, PF_RT_RTABLEID, r->rtableid);
 	snl_add_msg_attr_timeouts(nw, PF_RT_TIMEOUT, r->timeout);
@@ -1070,6 +1071,7 @@ snl_add_msg_attr_pf_rule(struct snl_writer *nw, uint32
 	snl_add_msg_attr_u8(nw, PF_RT_PRIO, r->prio);
 	snl_add_msg_attr_u8(nw, PF_RT_SET_PRIO, r->set_prio[0]);
 	snl_add_msg_attr_u8(nw, PF_RT_SET_PRIO_REPLY, r->set_prio[1]);
+	snl_add_msg_attr_u8(nw, PF_RT_NAF, r->naf);
 
 	snl_add_msg_attr_ip6(nw, PF_RT_DIVERT_ADDRESS, &r->divert.addr.v6);
 	snl_add_msg_attr_u16(nw, PF_RT_DIVERT_PORT, r->divert.port);
@@ -1322,12 +1324,14 @@ SNL_DECLARE_ATTR_PARSER(speer_parser, nla_p_speer);
 SNL_DECLARE_ATTR_PARSER(speer_parser, nla_p_speer);
 #undef _OUT
 
-#define	_OUT(_field)	offsetof(struct pf_state_key_export, _field)
+#define	_OUT(_field)	offsetof(struct pfctl_state_key, _field)
 static const struct snl_attr_parser nla_p_skey[] = {
 	{ .type = PF_STK_ADDR0, .off = _OUT(addr[0]), .cb = snl_attr_get_pfaddr },
 	{ .type = PF_STK_ADDR1, .off = _OUT(addr[1]), .cb = snl_attr_get_pfaddr },
 	{ .type = PF_STK_PORT0, .off = _OUT(port[0]), .cb = snl_attr_get_uint16 },
 	{ .type = PF_STK_PORT1, .off = _OUT(port[1]), .cb = snl_attr_get_uint16 },
+	{ .type = PF_STK_AF, .off = _OUT(af), .cb = snl_attr_get_uint8 },
+  { .type = PF_STK_PROTO, .off = _OUT(proto), .cb = snl_attr_get_uint16 },
 };
 SNL_DECLARE_ATTR_PARSER(skey_parser, nla_p_skey);
 #undef _OUT
@@ -1353,8 +1357,6 @@ static struct snl_attr_parser ap_state[] = {
 	{ .type = PF_ST_PACKETS1, .off = _OUT(packets[1]), .cb = snl_attr_get_uint64 },
 	{ .type = PF_ST_BYTES0, .off = _OUT(bytes[0]), .cb = snl_attr_get_uint64 },
 	{ .type = PF_ST_BYTES1, .off = _OUT(bytes[1]), .cb = snl_attr_get_uint64 },
-	{ .type = PF_ST_AF, .off = _OUT(key[0].af), .cb = snl_attr_get_uint8 },
-	{ .type = PF_ST_PROTO, .off = _OUT(key[0].proto), .cb = snl_attr_get_uint8 },
 	{ .type = PF_ST_DIRECTION, .off = _OUT(direction), .cb = snl_attr_get_uint8 },
 	{ .type = PF_ST_LOG, .off = _OUT(log), .cb = snl_attr_get_uint8 },
 	{ .type = PF_ST_STATE_FLAGS, .off = _OUT(state_flags), .cb = snl_attr_get_uint16 },
@@ -1407,9 +1409,6 @@ pfctl_get_states_nl(struct pfctl_state_filter *filter,
 		bzero(&s, sizeof(s));
 		if (!snl_parse_nlmsg(ss, hdr, &state_parser, &s))
 			continue;
-
-		s.key[1].af = s.key[0].af;
-		s.key[1].proto = s.key[0].proto;
 
 		ret = f(&s, arg);
 		if (ret != 0)
