--- dhcp6c_script.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6c_script.c
@@ -58,6 +58,7 @@
 
 #include "dhcp6.h"
 #include "config.h"
+#include "dhcp6c.h"
 #include "common.h"
 
 static char sipserver_str[] = "new_sip_servers";
@@ -71,12 +72,16 @@ static char nispserver_str[] = "new_nisp_servers";
 static char nispname_str[] = "new_nisp_name";
 static char bcmcsserver_str[] = "new_bcmcs_servers";
 static char bcmcsname_str[] = "new_bcmcs_name";
+static char raw_dhcp_option_str[] = "raw_dhcp_option";
 
+int client6_script(char *, int, struct dhcp6_optinfo *, struct dhcp6_if *);
+
 int
-client6_script(scriptpath, state, optinfo)
+client6_script(scriptpath, state, optinfo, ifp)
 	char *scriptpath;
 	int state;
 	struct dhcp6_optinfo *optinfo;
+	struct dhcp6_if *ifp;
 {
 	int i, dnsservers, ntpservers, dnsnamelen, envc, elen, ret = 0;
 	int sipservers, sipnamelen;
@@ -85,9 +90,19 @@ client6_script(scriptpath, state, optinfo)
 	int bcmcsservers, bcmcsnamelen;
 	char **envp, *s;
 	char reason[32];
+	/* need space for 32 + 7 : + 1 / + 1-3 prefixlen,
+	 * leave room for scope for %scope even though unused */
+	static char prefixinfo[64];
+	/* enuough space for ~1.5* a /56 worth of /64 ifs of 8 chars + sla_len:id */
+	static char prefixif[8192];
+	int prefixcount = 0;
 	struct dhcp6_listval *v;
 	struct dhcp6_event ev;
+	struct rawoption *rawop;
 	pid_t pid, wpid;
+	struct dhcp6_listval *iav, *siav;
+	struct iapd_conf *iapdc;
+	struct prefix_ifconf *pif;
 
 	/* if a script is not specified, do nothing */
 	if (scriptpath == NULL || strlen(scriptpath) == 0)
@@ -108,7 +123,7 @@ client6_script(scriptpath, state, optinfo)
 	nispnamelen = 0;
 	bcmcsservers = 0;
 	bcmcsnamelen = 0;
-	envc = 2;     /* we at least include the reason and the terminator */
+	envc = 5;     /* we at least include the reason, prefix count, duids, and the terminator */
 	if (state == DHCP6S_EXIT)
 		goto setenv;
 
@@ -160,6 +175,15 @@ client6_script(scriptpath, state, optinfo)
 	}
 	envc += bcmcsnamelen ? 1 : 0;
 
+	/* count the number of prefix delegations */
+	for (iav = TAILQ_FIRST(&optinfo->iapd_list); iav; iav = TAILQ_NEXT(iav, link)) {
+		for (siav = TAILQ_FIRST(&iav->sublist); siav; siav = TAILQ_NEXT(siav, link)) {
+			if (siav->type == DHCP6_LISTVAL_PREFIX6) {
+				envc += 2; /* prefix and interface vars */
+			}
+		}
+	}
+
 setenv:
 	/* allocate an environments array */
 	if ((envp = malloc(sizeof (char *) * envc)) == NULL) {
@@ -185,6 +209,92 @@ setenv:
 	if (state == DHCP6S_EXIT)
 		goto launch;
 
+	/* prefix delegations */
+	for (iav = TAILQ_FIRST(&optinfo->iapd_list); iav; iav = TAILQ_NEXT(iav, link)) {
+		if ((iapdc = (struct iapd_conf *)find_iaconf(
+				&ifp->iaconf_list, IATYPE_PD, iav->val_ia.iaid)) == NULL) {
+			continue;
+		}
+		for (siav = TAILQ_FIRST(&iav->sublist); siav; siav = TAILQ_NEXT(siav, link)) {
+			size_t if_left = sizeof(prefixif);
+			char *if_next = prefixif;
+			size_t ret = 0;
+			unsigned int if_count = 0;
+			if (siav->type == DHCP6_LISTVAL_PREFIX6) {
+				/* set first to PDINFO/PDINT */
+				if (!prefixcount) {
+					snprintf(prefixinfo, sizeof(prefixinfo),
+							"PDINFO=%s/%d",
+							in6addr2str(&siav->val_prefix6.addr, 0),
+							siav->val_prefix6.plen);
+					ret = strlcpy(prefixif, "PDIF", if_left);
+				} else {
+					snprintf(prefixinfo, sizeof(prefixinfo),
+							"PDINFO%d=%s/%d",
+								prefixcount,
+							in6addr2str(&siav->val_prefix6.addr, 0),
+							siav->val_prefix6.plen);
+					ret = snprintf(prefixif, if_left,
+							"PDIF%d", prefixcount);
+				}
+
+				if_next = prefixif + ret;
+				if_left -= ret;
+
+				for (pif = TAILQ_FIRST(&iapdc->iapd_pif_list); pif && if_left > 1;
+						pif = TAILQ_NEXT(pif, link)) {
+					/* finally the configured if name */
+					ret = snprintf(if_next, if_left, "%c%s,%s",
+						if_count ? ' ' : '=',
+						pif->ifname, pif->ifaddr
+						? addr2str((struct sockaddr *)pif->ifaddr) : "");
+					if_left -= ret;
+					if_next += ret;
+					if_count += 1;
+				}
+
+				if ((envp[i++] = strdup(prefixinfo)) == NULL) {
+					d_printf(LOG_NOTICE, FNAME, "failed to allocate prefixinfo strings");
+					ret = -1;
+					goto clean;
+				}
+
+				if (if_count) {
+					if ((envp[i++] = strdup(prefixif)) == NULL) {
+						d_printf(LOG_NOTICE, FNAME, "failed to allocate prefixif strings");
+						ret = -1;
+						goto clean;
+					}
+				}
+
+				prefixcount += 1;
+			}
+		}
+	}
+
+	snprintf(prefixinfo, sizeof(prefixinfo), "PDCOUNT=%d", prefixcount);
+	if ((envp[i++] = strdup(prefixinfo)) == NULL) {
+		d_printf(LOG_NOTICE, FNAME, "failed to allocate prefixinfo strings");
+		ret = -1;
+		goto clean;
+	}
+
+	elen = sizeof("xx:") * 128 + sizeof("...") + sizeof("CDUID=");
+	if ((s = envp[i++] = malloc(elen)) == NULL) {
+		d_printf(LOG_NOTICE, FNAME, "failed to allocate duid strings");
+		ret = -1;
+		goto clean;
+	}
+	snprintf(s, elen, "CDUID=%s", duidstr(&ifp->duid));
+
+	elen = sizeof("xx:") * 128 + sizeof("...") + sizeof("SDUID=");
+	if ((s = envp[i++] = malloc(elen)) == NULL) {
+		d_printf(LOG_NOTICE, FNAME, "failed to allocate duid strings");
+		ret = -1;
+		goto clean;
+	}
+	snprintf(s, elen, "SDUID=%s", duidstr(&optinfo->serverID));
+
 	/* "var=addr1 addr2 ... addrN" + null char for termination */
 	if (dnsservers) {
 		elen = sizeof (dnsserver_str) +
@@ -390,6 +500,31 @@ setenv:
 			strlcat(s, v->val_vbuf.dv_buf, elen);
 			strlcat(s, " ", elen);
 		}
+	}
+	/* XXX */
+	for (rawop = TAILQ_FIRST(&optinfo->rawops); rawop; rawop = TAILQ_NEXT(rawop, link)) {
+		// max of 5 numbers after last underscore (seems like max DHCPv6 option could be 65535)
+		elen = sizeof(raw_dhcp_option_str) + 1 /* underscore */ + 1 /* equals sign */ + 5;
+		elen += rawop->datalen * 2;
+		if ((s = envp[i++] = malloc(elen)) == NULL) {
+			d_printf(LOG_NOTICE, FNAME,
+			    "failed to allocate string for DHCPv6 option %d",
+			    rawop->opnum);
+			ret = -1;
+			goto clean;
+		}
+
+		// make raw options available as raw_dhcp_option_xyz=hexresponse
+		snprintf(s, elen, "%s_%d=", raw_dhcp_option_str, rawop->opnum);
+		const char * hex = "0123456789abcdef";
+		char * val = (char*)malloc(3);
+		for (int o = 0; o < rawop->datalen; o++) {
+			val[0] = hex[(rawop->data[o]>>4) & 0x0F];
+			val[1] = hex[(rawop->data[o]   ) & 0x0F];
+			val[2] = 0x00;
+			strlcat(s, val, 1);
+		}
+		free(val);
 	}
 launch:
 	/* launch the script */
