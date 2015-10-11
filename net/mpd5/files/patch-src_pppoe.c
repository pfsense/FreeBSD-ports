--- src/pppoe.c	2013-06-11 10:00:00.000000000 +0100
+++ src/pppoe.c	2015-09-22 20:49:57.000000000 +0100
@@ -50,12 +50,14 @@
 	char		hook[NG_HOOKSIZ];	/* hook on that node */
 	char		session[MAX_SESSION];	/* session name */
 	char		acname[PPPOE_SERVICE_NAME_SIZE];	/* AC name */
+	uint16_t	max_payload;		/* PPP-Max-Payload (RFC4638) */
 	u_char		peeraddr[6];		/* Peer MAC address */
 	char		real_session[MAX_SESSION]; /* real session name */
 	char		agent_cid[64];		/* Agent Circuit ID */
 	char		agent_rid[64];		/* Agent Remote ID */
 	u_char		incoming;		/* incoming vs. outgoing */
 	u_char		opened;			/* PPPoE opened by phys */
+	u_char		mp_reply;		/* PPP-Max-Payload reply from server */
 	struct optinfo	options;
 	struct PppoeIf  *PIf;			/* pointer on parent ng_pppoe info */
 	struct PppoeList *list;
@@ -69,7 +71,8 @@
 enum {
 	SET_IFACE,
 	SET_SESSION,
-	SET_ACNAME
+	SET_ACNAME,
+	SET_MAX_PAYLOAD
 };
 
 /*
@@ -113,6 +116,8 @@
 static int	PppoeCalledNum(Link l, void *buf, size_t buf_len);
 static int	PppoeSelfName(Link l, void *buf, size_t buf_len);
 static int	PppoePeerName(Link l, void *buf, size_t buf_len);
+static u_short	PppoeGetMtu(Link l, int conf);
+static u_short	PppoeGetMru(Link l, int conf);
 static void	PppoeCtrlReadEvent(int type, void *arg);
 static void	PppoeConnectTimeout(void *arg);
 static void	PppoeStat(Context ctx);
@@ -155,6 +160,8 @@
     .callednum		= PppoeCalledNum,
     .selfname		= PppoeSelfName,
     .peername		= PppoePeerName,
+    .getmtu		= PppoeGetMtu,
+    .getmru		= PppoeGetMru
 };
 
 const struct cmdtab PppoeSetCmds[] = {
@@ -164,6 +171,10 @@
 	  PppoeSetCommand, NULL, 2, (void *)SET_SESSION },
       { "acname {name}",	"Set PPPoE access concentrator name",
 	  PppoeSetCommand, NULL, 2, (void *)SET_ACNAME },
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+      { "max-payload {size}",	"Set PPP-Max-Payload tag",
+	  PppoeSetCommand, NULL, 2, (void *)SET_MAX_PAYLOAD },
+#endif
       { NULL },
 };
 
@@ -213,6 +224,8 @@
 	pe->agent_cid[0] = 0;
 	pe->agent_rid[0] = 0;
 	pe->PIf = NULL;
+	pe->max_payload = 0;
+	pe->mp_reply = 0;
 
 	/* Done */
 	return(0);
@@ -327,6 +340,20 @@
     		    l->name, path, cn.ourhook, cn.path, cn.peerhook);
 		goto fail2;
 	}
+	
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	const uint16_t max_payload = pe->max_payload;
+	if (pe->max_payload > 0) {
+	    Log(LG_PHYS, ("[%s] PPPoE: Set PPP-Max-Payload to '%d'",
+		l->name, max_payload));
+	}
+	/* Tell the PPPoE node to set PPP-Max-Payload value (unset if 0). */
+	if (NgSendMsg(pe->PIf->csock, path, NGM_PPPOE_COOKIE, NGM_PPPOE_SETMAXP,
+	    &max_payload, sizeof(uint16_t)) < 0) {
+		Perror("[%s] PPPoE can't set PPP-Max-Payload value", l->name);
+		goto fail2;
+	}
+#endif
 
 	Log(LG_PHYS, ("[%s] PPPoE: Connecting to '%s'", l->name, pe->session));
 	
@@ -351,6 +378,7 @@
 	strlcpy(pe->real_session, pe->session, sizeof(pe->real_session));
 	pe->agent_cid[0] = 0;
 	pe->agent_rid[0] = 0;
+	pe->mp_reply = 0;
 	return;
 
 fail3:
@@ -433,6 +461,7 @@
 	pi->real_session[0] = 0;
 	pi->agent_cid[0] = 0;
 	pi->agent_rid[0] = 0;
+	pi->mp_reply = 0;
 }
 
 /*
@@ -444,7 +473,11 @@
 PppoeCtrlReadEvent(int type, void *arg)
 {
 	union {
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	    u_char buf[sizeof(struct ng_mesg) + sizeof(struct ngpppoe_maxp)];
+#else
 	    u_char buf[sizeof(struct ng_mesg) + sizeof(struct ngpppoe_sts)];
+#endif
 	    struct ng_mesg resp;
 	} u;
 	char path[NG_PATHSIZ];
@@ -468,6 +501,9 @@
 	    case NGM_PPPOE_SUCCESS:
 	    case NGM_PPPOE_FAIL:
 	    case NGM_PPPOE_CLOSE:
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	    case NGM_PPPOE_SETMAXP:
+#endif
 	    {
 		char	ppphook[NG_HOOKSIZ];
 		char	*linkname, *rest;
@@ -535,6 +571,28 @@
 		Log(LG_PHYS, ("PPPoE: rec'd ACNAME \"%s\"",
 		  ((struct ngpppoe_sts *)u.resp.data)->hook));
 		break;
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	    case NGM_PPPOE_SETMAXP:
+	    {
+		struct ngpppoe_maxp *maxp;
+		
+		maxp = ((struct ngpppoe_maxp *)u.resp.data);
+		Log(LG_PHYS, ("[%s] PPPoE: rec'd PPP-Max-Payload '%u'",
+		  l->name, maxp->data));
+		if (pi->max_payload > 0) {
+		    if (pi->max_payload == maxp->data)
+			pi->mp_reply = 1;
+		    else
+			Log(LG_PHYS,
+			  ("[%s] PPPoE: sent and returned values are not equal",
+			  l->name));
+		} else
+		    Log(LG_PHYS, ("[%s] PPPoE: server sent tag PPP-Max-Payload"
+		      " without request from the client",
+		      l->name));
+		break;
+	    }
+#endif
 	    default:
 		Log(LG_PHYS, ("PPPoE: rec'd command %lu from \"%s\"",
 		    (u_long)u.resp.header.cmd, path));
@@ -555,6 +613,9 @@
 	Printf("\tIface Node   : %s\r\n", pe->path);
 	Printf("\tIface Hook   : %s\r\n", pe->hook);
 	Printf("\tSession      : %s\r\n", pe->session);
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	Printf("\tMax-Payload  : %u\r\n", pe->max_payload);
+#endif
 	Printf("PPPoE status:\r\n");
 	if (ctx->lnk->state != PHYS_STATE_DOWN) {
 	    Printf("\tOpened       : %s\r\n", (pe->opened?"YES":"NO"));
@@ -562,6 +623,7 @@
 	    PppoePeerMacAddr(ctx->lnk, buf, sizeof(buf));
 	    Printf("\tCurrent peer : %s\r\n", buf);
 	    Printf("\tSession      : %s\r\n", pe->real_session);
+	    Printf("\tMax-Payload  : %s\r\n", (pe->mp_reply?"YES":"NO"));
 	    Printf("\tCircuit-ID   : %s\r\n", pe->agent_cid);
 	    Printf("\tRemote-ID    : %s\r\n", pe->agent_rid);
 	}
@@ -657,6 +719,34 @@
 	return (0);
 }
 
+static u_short
+PppoeGetMtu(Link l, int conf)
+{
+	PppoeInfo	const pppoe = (PppoeInfo)l->info;
+
+	if (pppoe->max_payload > 0 && pppoe->mp_reply > 0)
+	    return (pppoe->max_payload);
+	else
+	    if (conf == 0)
+		return (l->type->mtu);
+	    else
+		return (l->conf.mtu);
+}
+
+static u_short
+PppoeGetMru(Link l, int conf)
+{
+	PppoeInfo	const pppoe = (PppoeInfo)l->info;
+
+	if (pppoe->max_payload > 0 && pppoe->mp_reply > 0)
+	    return (pppoe->max_payload);
+	else
+	    if (conf == 0)
+		return (l->type->mru);
+	    else
+		return (l->conf.mru);
+}
+
 static int 
 CreatePppoeNode(struct PppoeIf *PIf, const char *path, const char *hook)
 {
@@ -1340,7 +1430,9 @@
 	const PppoeInfo pi = (PppoeInfo) ctx->lnk->info;
 	const char *hookname = ETHER_DEFAULT_HOOK;
 	const char *colon;
-
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	int ap;
+#endif
 	switch ((intptr_t)arg) {
 	case SET_IFACE:
 		switch (ac) {
@@ -1377,6 +1469,16 @@
 			return(-1);
 		strlcpy(pi->acname, av[0], sizeof(pi->acname));
 		break;
+#ifdef NGM_PPPOE_SETMAXP_COOKIE
+	case SET_MAX_PAYLOAD:
+		if (ac != 1)
+			return(-1);
+		ap = atoi(av[0]);
+		if (ap < PPPOE_MRU || ap > ETHER_MAX_LEN - 8)
+			Error("PPP-Max-Payload value \"%s\"", av[0]);
+		pi->max_payload = ap;
+		break;
+#endif
 	default:
 		assert(0);
 	}
