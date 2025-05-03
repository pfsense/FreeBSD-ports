--- common/bpf.c.orig	2020-09-17 06:51:06.537106000 +0200
+++ common/bpf.c	2020-09-17 06:52:05.627807000 +0200
@@ -197,6 +197,7 @@
 	/* Otherwise, drop it. */
 	BPF_STMT (BPF_RET + BPF_K, 0),
 };
+struct bpf_insn * dhcp_virtual_filter = NULL;
 
 #if defined(RELAY_PORT)
 /*
@@ -235,6 +236,8 @@
 
 int dhcp_bpf_relay_filter_len =
 	sizeof dhcp_bpf_relay_filter / sizeof (struct bpf_insn);
+
+struct bpf_insn * dhcp_virtual_relay_filter = NULL;
 #endif
 
 #if defined (DEC_FDDI)
@@ -242,6 +245,7 @@
 #endif
 
 int dhcp_bpf_filter_len = sizeof dhcp_bpf_filter / sizeof (struct bpf_insn);
+
 #if defined (HAVE_TR_SUPPORT)
 struct bpf_insn dhcp_bpf_tr_filter [] = {
         /* accept all token ring packets due to variable length header */
@@ -259,7 +263,88 @@
 #endif /* HAVE_TR_SUPPORT */
 #endif /* USE_LPF_RECEIVE || USE_BPF_RECEIVE */
 
+void patch_virtual_filter(filter)
+	struct bpf_insn * filter;
+{
+	/* The address family is 4 bytes long */
+	filter[0].k = 0;
+	filter[0].code = BPF_LD + BPF_W + BPF_ABS;
+
+        /* From Kea:
+		The address family is AF_INET. It can't be hardcoded in the BPF program
+		because we need to make the host to network order conversion using htonl
+		and conversion can't be done within the BPF program structure as it
+		doesn't work on some systems. */
+	filter[1].k = htonl(AF_INET);
+
+	/* Patch the BPF program to account for the difference
+		in length between ethernet headers (14), and virtual
+		link headers (4, -10).
+		XXX changes to filter program may require changes to
+		XXX the insn number(s) used below! */
+	filter[2].k -= 10;
+	filter[4].k -= 10;
+	filter[6].k -= 10;
+	filter[7].k -= 10;
+}
+
+struct bpf_insn * get_virtual_filter()
+{
+	if ( dhcp_virtual_filter == NULL ) {
+		dhcp_virtual_filter = dmalloc (sizeof dhcp_bpf_filter, MDL);
+		if (!dhcp_virtual_filter)
+			log_fatal ("No memory for virtual filter.");
+		memcpy (dhcp_virtual_filter, dhcp_bpf_filter, sizeof dhcp_bpf_filter);
+		patch_virtual_filter(dhcp_virtual_filter);
+	}
+	return dhcp_virtual_filter;
+}
+
+#if defined(RELAY_PORT)
+struct bpf_insn * get_virtual_relay_filter()
+{
+	if ( dhcp_virtual_relay_filter == NULL ) {
+		dhcp_virtual_relay_filter = dmalloc (sizeof dhcp_bpf_relay_filter, MDL);
+		if (!dhcp_virtual_relay_filter)
+			log_fatal ("No memory for virtual relay filter.");
+		memcpy (dhcp_virtual_relay_filter, dhcp_bpf_relay_filter, sizeof dhcp_bpf_relay_filter);
+		patch_virtual_filter(dhcp_virtual_relay_filter);
+	}
+	return dhcp_virtual_relay_filter;
+}
+#endif
+
 #if defined (USE_BPF_RECEIVE)
+
+static int is_virtual(name)
+	const char * name;
+{
+	int ret = 0;
+	struct ifaddrs *ifa;
+	struct ifaddrs *p;
+	struct sockaddr_dl *sa;
+
+	if (getifaddrs(&ifa) != 0) {
+		log_fatal("Error getting interface information; %m");
+	}
+
+	/*
+	* Loop through our interfaces finding a match.
+	*/
+	sa = NULL;
+	for (p=ifa; (p != NULL) && (sa == NULL); p = p->ifa_next) {
+		if ((p->ifa_addr->sa_family == AF_LINK) && !strcmp(p->ifa_name, name)) {
+			sa = (struct sockaddr_dl *)p->ifa_addr;
+		}
+	}
+
+	if (sa != NULL && sa->sdl_type == IFT_TUNNEL )
+		ret = 1;
+
+	freeifaddrs(ifa);
+	return ret;
+}
+
 void if_register_receive (info)
 	struct interface_info *info;
 {
@@ -272,6 +357,9 @@
 #ifdef DEC_FDDI
 	int link_layer;
 #endif /* DEC_FDDI */
+	int virtual = 0;
+
+	virtual = is_virtual(info->name);
 
 	/* Open a BPF device and hang it on this interface... */
 	info -> rfdesc = if_register_bpf (info);
@@ -343,7 +431,10 @@
 		p.bf_insns = bpf_fddi_filter;
 	} else
 #endif /* DEC_FDDI */
-	p.bf_insns = dhcp_bpf_filter;
+	if ( virtual )
+		p.bf_insns = get_virtual_filter();
+	else
+		p.bf_insns = dhcp_bpf_filter;
 
         /* Patch the server port into the BPF  program...
 	   XXX changes to filter program may require changes
@@ -355,9 +446,12 @@
 		 * also on the user UDP port.
 		 */
 		p.bf_len = dhcp_bpf_relay_filter_len;
-		p.bf_insns = dhcp_bpf_relay_filter;
+		if ( virtual )
+			p.bf_insns = get_virtual_relay_filter();
+		else
+			p.bf_insns = dhcp_bpf_relay_filter;
 
-		dhcp_bpf_relay_filter [10].k = ntohs (relay_port);
+		p.bf_insns [10].k = ntohs (relay_port);
 	}
 #endif
 	p.bf_insns [8].k = ntohs (local_port);
@@ -627,7 +721,11 @@
 	 * Pull out the appropriate information.
 	 */
         switch (sa->sdl_type) {
-                case IFT_ETHER:
+		case IFT_TUNNEL:
+                        hw->hlen = 1;
+                        hw->hbuf[0] = HTYPE_VIRTUAL;
+                        break;
+		case IFT_ETHER:
 #if defined (IFT_L2VLAN)
 		case IFT_L2VLAN:
 #endif

