--- config.c.orig	2017-02-28 19:06:15 UTC
+++ config.c
@@ -53,12 +53,12 @@
 #include <time.h>
 #endif
 
-#include <dhcp6.h>
-#include <config.h>
-#include <common.h>
-#include <auth.h>
-#include <base64.h>
-#include <lease.h>
+#include "dhcp6.h"
+#include "config.h"
+#include "common.h"
+#include "auth.h"
+#include "base64.h"
+#include "lease.h"
 
 extern int errno;
 
@@ -70,7 +70,7 @@ struct dhcp6_list bcmcslist, bcmcsnamelist;
 long long optrefreshtime;
 
 static struct dhcp6_ifconf *dhcp6_ifconflist;
-struct ia_conflist ia_conflist0;
+static struct ia_conflist ia_conflist0;
 static struct host_conf *host_conflist0, *host_conflist;
 static struct keyinfo *key_list, *key_list0;
 static struct authinfo *auth_list, *auth_list0;
@@ -107,6 +107,9 @@ struct dhcp6_ifconf {
 
 	char *scriptpath;	/* path to config script (client only) */
 
+	struct duid duid;
+	struct rawop_list rawops;
+
 	struct dhcp6_list reqopt_list;
 	struct ia_conflist iaconf_list;
 
@@ -123,27 +126,89 @@ extern struct cf_list *cf_bcmcs_list, *cf_bcmcs_name_l
 extern long long cf_refreshtime;
 extern char *configfilename;
 
-static struct keyinfo *find_keybyname __P((struct keyinfo *, char *));
-static int add_pd_pif __P((struct iapd_conf *, struct cf_list *));
-static int add_options __P((int, struct dhcp6_ifconf *, struct cf_list *));
-static int add_prefix __P((struct dhcp6_list *, char *, int,
-    struct dhcp6_prefix *));
-static void clear_pd_pif __P((struct iapd_conf *));
-static void clear_ifconf __P((struct dhcp6_ifconf *));
-static void clear_iaconf __P((struct ia_conflist *));
-static void clear_hostconf __P((struct host_conf *));
-static void clear_keys __P((struct keyinfo *));
-static void clear_authinfo __P((struct authinfo *));
-static int configure_duid __P((char *, struct duid *));
-static int configure_addr __P((struct cf_list *, struct dhcp6_list *, char *));
-static int configure_domain __P((struct cf_list *, struct dhcp6_list *, char *));
-static int get_default_ifid __P((struct prefix_ifconf *));
-static void clear_poolconf __P((struct pool_conf *));
-static struct pool_conf *create_pool __P((char *, struct dhcp6_range *));
-struct host_conf *find_dynamic_hostconf __P((struct duid *));
-static int in6_addr_cmp __P((struct in6_addr *, struct in6_addr *));
-static void in6_addr_inc __P((struct in6_addr *));
+static struct keyinfo *find_keybyname(struct keyinfo *, char *);
+static int add_pd_pif(struct iapd_conf *, struct cf_list *);
+static int add_options(int, struct dhcp6_ifconf *, struct cf_list *);
+static int add_prefix(struct dhcp6_list *, const char *, int,
+    struct dhcp6_prefix *);
+static void clear_pd_pif(struct iapd_conf *);
+static void clear_ifconf(struct dhcp6_ifconf *);
+static void clear_iaconf(struct ia_conflist *);
+static void clear_hostconf(struct host_conf *);
+static void clear_keys(struct keyinfo *);
+static void clear_authinfo(struct authinfo *);
+static int configure_duid(char *, struct duid *);
+static int configure_addr(struct cf_list *, struct dhcp6_list *, const char *);
+static int configure_domain(struct cf_list *, struct dhcp6_list *,
+    const char *);
+static int get_default_ifid(struct prefix_ifconf *);
+static void clear_poolconf(struct pool_conf *);
+static struct pool_conf *create_pool(char *, struct dhcp6_range *);
+struct host_conf *find_dynamic_hostconf(struct duid *);
+static int in6_addr_cmp(struct in6_addr *, struct in6_addr *);
+static void in6_addr_inc(struct in6_addr *);
 
+/* a debug helper to complete someday if needed... or delete*/
+void list_cfl (char *tag,struct cf_namelist *head)
+{
+	struct cf_namelist *ifp;
+	printf("LIST CFL %s\n",tag);
+
+	for (ifp = head; ifp; ifp = ifp->next) {
+		printf("Do ifp->%s\n", ifp->name);
+		struct cf_list *cfl;
+		for (cfl = ifp->params; cfl; cfl = cfl->next) {
+			printf("Do cfltype->%i  lineconf %i  ptr=%p sublist=%p\n", cfl->type,cfl->line, cfl->ptr, cfl->list);
+			    switch(cfl->type) {
+
+				case DECL_SEND:{
+					struct cf_list *cf0 = (void*)cfl->list;
+					for (; cf0; cf0 = cf0->next) {
+					    //printf("SEND opt type %i\n",cf0->type);
+					    switch (cf0->type) {
+					    case DHCPOPT_RAW:{
+						    struct rawoption *op = cf0->ptr;
+						    printf("rawop %i datalen %i\n", op->opnum, op->datalen);
+						}
+						break;
+					    default:;
+					    }
+					}
+					}
+					break;
+				case DECL_SCRIPT:
+					printf("script %s\n", cfl->ptr);
+					break;
+				default:
+					printf("Unknown option type %i\n", cfl->type);
+			    }
+/*
+		case DHCPOPT_RAW:
+		case DHCPOPT_RAPID_COMMIT:
+		case DHCPOPT_AUTHINFO:
+		case DHCPOPT_IA_PD:
+		case DHCPOPT_IA_NA:
+		case DHCPOPT_SIP:
+		case DHCPOPT_SIPNAME:
+		case DHCPOPT_DNS:
+		case DHCPOPT_DNSNAME:
+		case DHCPOPT_NTP:
+		case DHCPOPT_NIS:
+		case DHCPOPT_NISNAME:
+		case DHCPOPT_NISP:
+		case DHCPOPT_NISPNAME:
+		case DHCPOPT_BCMCS:
+		case DHCPOPT_BCMCSNAME:
+		case DHCPOPT_REFRESHTIME:
+			printf("Known option type %i\n", cfl->type);
+			break;
+		default:
+			printf("Unknown option type %i\n", cfl->type);
+*/
+		}
+	}
+}
+
 int
 configure_interface(iflist)
 	struct cf_namelist *iflist;
@@ -178,6 +243,7 @@ configure_interface(iflist)
 		ifc->server_pref = DH6OPT_PREF_UNDEF;
 		TAILQ_INIT(&ifc->reqopt_list);
 		TAILQ_INIT(&ifc->iaconf_list);
+		TAILQ_INIT(&ifc->rawops);
 
 		for (cfl = ifp->params; cfl; cfl = cfl->next) {
 			switch(cfl->type) {
@@ -206,6 +272,20 @@ configure_interface(iflist)
 					goto bad;
 				}
 				break;
+			case DECL_DUID:
+				if ((configure_duid((char *)cfl->ptr,
+						    &ifc->duid)) != 0) {
+					d_printf(LOG_ERR, FNAME, "%s:%d "
+					    "failed to configure "
+					    "DUID for %s",
+					    configfilename, cfl->line,
+					    ifc->ifname);
+					goto bad;
+				}
+				d_printf(LOG_DEBUG, FNAME,
+				    "configure DUID for %s: %s",
+				    ifc->ifname, duidstr(&ifc->duid));
+				break;
 			case DECL_INFO_ONLY:
 				if (dhcp6_mode != DHCP6_MODE_CLIENT) {
 					d_printf(LOG_INFO, FNAME, "%s:%d "
@@ -305,8 +385,16 @@ configure_interface(iflist)
 				goto bad;
 			}
 		}
+
+		if (use_all_config_if) {
+			if (ifinit(ifp->name) == NULL) {
+				d_printf(LOG_ERR, FNAME, "failed to initialize %s", ifp->name);
+				/* safe to exit here as still parsing */
+				exit(1);
+			}
+		}
 	}
-	
+
 	return (0);
 
   bad:
@@ -355,7 +443,7 @@ configure_ia(ialist, iatype)
 
 		/* common initialization */
 		iac->type = iatype;
-		iac->iaid = (u_int32_t)atoi(iap->name);
+		iac->iaid = (uint32_t)atoi(iap->name);
 		TAILQ_INIT(&iac->iadata);
 		TAILQ_INSERT_TAIL(&ia_conflist0, iac, link);
 
@@ -480,7 +568,7 @@ add_pd_pif(iapdc, cfl0)
 	for (cfl = cfl0->list; cfl; cfl = cfl->next) {
 		switch(cfl->type) {
 		case IFPARAM_SLA_ID:
-			pif->sla_id = (u_int32_t)cfl->num;
+			pif->sla_id = (uint32_t)cfl->num;
 			break;
 		case IFPARAM_SLA_LEN:
 			pif->sla_len = (int)cfl->num;
@@ -809,7 +897,7 @@ configure_keys(keylist)
 				}
 				lt = localtime(&now);
 				lt->tm_sec = 0;
-				
+
 				if (strptime(expire, "%Y-%m-%d %H:%M", lt)
 				    == NULL &&
 				    strptime(expire, "%m-%d %H:%M", lt)
@@ -1033,7 +1121,7 @@ static int
 configure_addr(cf_addr_list, list0, optname)
 	struct cf_list *cf_addr_list;
 	struct dhcp6_list *list0;
-	char *optname;
+	const char *optname;
 {
 	struct cf_list *cl;
 
@@ -1071,7 +1159,7 @@ static int
 configure_domain(cf_name_list, list0, optname)
 	struct cf_list *cf_name_list;
 	struct dhcp6_list *list0;
-	char *optname;
+	const char *optname;
 {
 	struct cf_list *cl;
 
@@ -1087,7 +1175,7 @@ configure_domain(cf_name_list, list0, optname)
 		char *name, *cp;
 		struct dhcp6_vbuf name_vbuf;
 
-		name = strdup(cl->ptr + 1);
+		name = strdup((char *)cl->ptr + 1);
 		if (name == NULL) {
 			d_printf(LOG_ERR, FNAME,
 			    "failed to copy a %s domain name",
@@ -1216,7 +1304,7 @@ get_default_ifid(pif)
 		if (ifa->ifa_addr->sa_family != AF_LINK)
 			continue;
 
-		sdl = (struct sockaddr_dl *)ifa->ifa_addr;
+		sdl = (struct sockaddr_dl *)(void *)ifa->ifa_addr;
 		if (sdl->sdl_alen < 6) {
 			d_printf(LOG_NOTICE, FNAME,
 			    "link layer address is too short (%s)",
@@ -1309,13 +1397,15 @@ configure_commit()
 		ifp->send_flags = 0;
 		ifp->allow_flags = 0;
 		dhcp6_clear_list(&ifp->reqopt_list);
+		rawop_clear_list(&ifp->rawops);
 		clear_iaconf(&ifp->iaconf_list);
+
 		ifp->server_pref = DH6OPT_PREF_UNDEF;
 		if (ifp->scriptpath != NULL)
 			free(ifp->scriptpath);
 		ifp->scriptpath = NULL;
 		ifp->authproto = DHCP6_AUTHPROTO_UNDEF;
-		ifp->authalgorithm = DHCP6_AUTHALG_UNDEF; 
+		ifp->authalgorithm = DHCP6_AUTHALG_UNDEF;
 		ifp->authrdm = DHCP6_AUTHRDM_UNDEF;
 
 		for (ifc = dhcp6_ifconflist; ifc; ifc = ifc->next) {
@@ -1329,6 +1419,7 @@ configure_commit()
 		ifp->send_flags = ifc->send_flags;
 		ifp->allow_flags = ifc->allow_flags;
 		dhcp6_copy_list(&ifp->reqopt_list, &ifc->reqopt_list);
+		rawop_copy_list(&ifp->rawops, &ifc->rawops);
 		while ((iac = TAILQ_FIRST(&ifc->iaconf_list)) != NULL) {
 			TAILQ_REMOVE(&ifc->iaconf_list, iac, link);
 			TAILQ_INSERT_TAIL(&ifp->iaconf_list,
@@ -1345,8 +1436,15 @@ configure_commit()
 		}
 		ifp->pool = ifc->pool;
 		ifc->pool.name = NULL;
+
+		if (ifc->duid.duid_id != NULL) {
+			d_printf(LOG_INFO, FNAME, "copying duid");
+			duidcpy(&ifp->duid, &ifc->duid);
+			duidfree(&ifc->duid);
+		}
 	}
 
+
 	clear_ifconf(dhcp6_ifconflist);
 	dhcp6_ifconflist = NULL;
 
@@ -1435,6 +1533,7 @@ clear_ifconf(iflist)
 
 		free(ifc->ifname);
 		dhcp6_clear_list(&ifc->reqopt_list);
+		rawop_clear_list(&ifc->rawops);
 
 		clear_iaconf(&ifc->iaconf_list);
 
@@ -1592,7 +1691,7 @@ add_options(opcode, ifc, cfl0)
 			switch (opcode) {
 			case DHCPOPTCODE_SEND:
 				iac = find_iaconf(&ia_conflist0, IATYPE_PD,
-				    (u_int32_t)cfl->num);
+				    (uint32_t)cfl->num);
 				if (iac == NULL) {
 					d_printf(LOG_ERR, FNAME, "%s:%d "
 					    "IA_PD (%lu) is not defined",
@@ -1617,7 +1716,7 @@ add_options(opcode, ifc, cfl0)
 			switch (opcode) {
 			case DHCPOPTCODE_SEND:
 				iac = find_iaconf(&ia_conflist0, IATYPE_NA,
-				    (u_int32_t)cfl->num);
+				    (uint32_t)cfl->num);
 				if (iac == NULL) {
 					d_printf(LOG_ERR, FNAME, "%s:%d "
 					    "IA_NA (%lu) is not defined",
@@ -1638,6 +1737,37 @@ add_options(opcode, ifc, cfl0)
 				break;
 			}
 			break;
+
+		case DHCPOPT_RAW:
+			opttype = DHCPOPT_RAW;
+			struct rawoption *newop, *op;
+			op = (struct rawoption *) cfl->ptr;
+			d_printf(LOG_INFO, FNAME,
+				"add raw option: %d length: %d",
+				op->opnum, op->datalen);
+
+			if ((newop = malloc(sizeof(*newop))) == NULL) {
+				d_printf(LOG_ERR, FNAME,
+					"failed to allocate memory for a new raw option");
+				return(-1);
+			}
+
+			memset(newop, 0, sizeof(*newop));
+
+			newop->opnum = op->opnum;
+			newop->datalen = op->datalen;
+
+			/* copy data */
+			if ((newop->data = malloc(newop->datalen)) == NULL) {
+				d_printf(LOG_ERR, FNAME,
+				    "failed to allocate memory for new raw option data");
+				return(-1);
+			}
+			memcpy(newop->data, op->data, newop->datalen);
+
+			TAILQ_INSERT_TAIL(&ifc->rawops, newop, link);
+			break;
+
 		case DHCPOPT_SIP:
 		case DHCPOPT_SIPNAME:
 		case DHCPOPT_DNS:
@@ -1730,7 +1860,7 @@ add_options(opcode, ifc, cfl0)
 static int
 add_prefix(head, name, type, prefix0)
 	struct dhcp6_list *head;
-	char *name;
+	const char *name;
 	int type;
 	struct dhcp6_prefix *prefix0;
 {
@@ -1806,7 +1936,7 @@ struct ia_conf *
 find_iaconf(head, type, iaid)
 	struct ia_conflist *head;
 	int type;
-	u_int32_t iaid;
+	uint32_t iaid;
 {
 	struct ia_conf *iac;
 
@@ -1874,7 +2004,7 @@ struct keyinfo *
 find_key(realm, realmlen, id)
 	char *realm;
 	size_t realmlen;
-	u_int32_t id;
+	uint32_t id;
 {
 	struct keyinfo *key;
 
@@ -1987,7 +2117,7 @@ create_dynamic_hostconf(duid, pool)
 {
 	struct dynamic_hostconf *dynconf = NULL;
 	struct host_conf *host;
-	char* strid = NULL;
+	const char *strid = NULL;
 	static int init = 1;
 
 	if (init) {
