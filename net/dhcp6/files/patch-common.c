--- common.c.orig	2017-02-28 19:06:15 UTC
+++ common.c
@@ -81,17 +81,17 @@
 #include <netdb.h>
 #include <ifaddrs.h>
 
-#include <dhcp6.h>
-#include <config.h>
-#include <common.h>
-#include <timer.h>
+#include "dhcp6.h"
+#include "config.h"
+#include "common.h"
+#include "timer.h"
 
 #ifdef __linux__
 /* from /usr/include/linux/ipv6.h */
 
 struct in6_ifreq {
 	struct in6_addr ifr6_addr;
-	u_int32_t ifr6_prefixlen;
+	uint32_t ifr6_prefixlen;
 	unsigned int ifr6_ifindex;
 };
 #endif
@@ -101,23 +101,119 @@ struct in6_ifreq {
 int foreground;
 int debug_thresh;
 
-static int dhcp6_count_list __P((struct dhcp6_list *));
-static int in6_matchflags __P((struct sockaddr *, char *, int));
-static ssize_t dnsencode __P((const char *, char *, size_t));
-static char *dnsdecode __P((u_char **, u_char *, char *, size_t));
-static int copyout_option __P((char *, char *, struct dhcp6_listval *));
-static int copyin_option __P((int, struct dhcp6opt *, struct dhcp6opt *,
-    struct dhcp6_list *));
-static int copy_option __P((u_int16_t, u_int16_t, void *, struct dhcp6opt **,
-    struct dhcp6opt *, int *));
-static ssize_t gethwid __P((char *, int, const char *, u_int16_t *));
-static char *sprint_uint64 __P((char *, int, u_int64_t));
-static char *sprint_auth __P((struct dhcp6_optinfo *));
+static int dhcp6_count_list(struct dhcp6_list *);
+static int in6_matchflags(struct sockaddr *, char *, int);
+static ssize_t dnsencode(const char *, char *, size_t);
+static char *dnsdecode(u_char **, u_char *, char *, size_t);
+static int copyout_option(char *, char *, struct dhcp6_listval *);
+static int copyin_option(int, struct dhcp6opt *, struct dhcp6opt *,
+    struct dhcp6_list *);
+static int copy_option(uint16_t, uint16_t, void *, struct dhcp6opt **,
+    struct dhcp6opt *, int *);
+static ssize_t gethwid(char *, int, const char *, uint16_t *);
+static char *sprint_uint64(char *, int, uint64_t);
+static char *sprint_auth(struct dhcp6_optinfo *);
 
 int
-dhcp6_copy_list(dst, src)
-	struct dhcp6_list *dst, *src;
+rawop_count_list(head)
+	struct rawop_list *head;
 {
+	struct rawoption *op;
+	int i;
+
+	//d_printf(LOG_INFO, FNAME, "counting list at %p", (void*)head);
+
+	for (i = 0, op = TAILQ_FIRST(head); op; op = TAILQ_NEXT(op, link)) {
+		i++;
+	}
+
+	return (i);
+}
+
+void
+rawop_clear_list(head)
+	struct rawop_list *head;
+{
+	struct rawoption *op;
+
+	//d_printf(LOG_INFO, FNAME, "clearing %d rawops at %p", rawop_count_list(head), (void*)head);
+
+	while ((op = TAILQ_FIRST(head)) != NULL) {
+
+		//d_printf(LOG_INFO, FNAME, "  current op: %p link: %p", (void*)op, op->link);
+		TAILQ_REMOVE(head, op, link);
+
+		if (op->data != NULL) {
+			d_printf(LOG_INFO, FNAME, "    freeing op data at %p", (void*)op->data);
+			free(op->data);
+		}
+		free(op);	// Needed? yes
+	}
+	return;
+}
+
+int
+rawop_copy_list(dst, src)
+	struct rawop_list *dst, *src;
+{
+	struct rawoption *op, *newop;
+
+	/*
+	d_printf(LOG_INFO, FNAME,
+		"  copying rawop list %p to %p (%d ops)",
+		(void*)src, (void*)dst, rawop_count_list(src));
+	*/
+
+	for (op = TAILQ_FIRST(src); op; op = TAILQ_NEXT(op, link)) {
+		newop = NULL;
+		if ((newop = malloc(sizeof(*newop))) == NULL) {
+			d_printf(LOG_ERR, FNAME,
+				"failed to allocate memory for a new raw option");
+			goto fail;
+		}
+		memset(newop, 0, sizeof(*newop));
+
+		newop->opnum = op->opnum;
+		newop->datalen = op->datalen;
+		newop->data = NULL;
+
+		/* copy data */
+		if ((newop->data = malloc(newop->datalen)) == NULL) {
+			d_printf(LOG_ERR, FNAME,
+				"failed to allocate memory for new raw option data");
+			goto fail;
+		}
+		memcpy(newop->data, op->data, newop->datalen);
+		//d_printf(LOG_INFO, FNAME, "    copied %d bytes of data at %p", newop->datalen, (void*)newop->data);
+
+		TAILQ_INSERT_TAIL(dst, newop, link);
+	}
+	return (0);
+
+  fail:
+	rawop_clear_list(dst);
+	return (-1);
+}
+
+void
+rawop_move_list(dst, src)
+	struct rawop_list *dst, *src;
+{
+	struct rawoption *op;
+	/*
+	d_printf(LOG_INFO, FNAME,
+		"  moving rawop list of %d from %p to %p",
+		rawop_count_list(src), (void*)src, (void*)dst);
+	*/
+	while ((op = TAILQ_FIRST(src)) != NULL) {
+		TAILQ_REMOVE(src, op, link);
+		TAILQ_INSERT_TAIL(dst, op, link);
+	}
+}
+
+int
+dhcp6_copy_list(struct dhcp6_list *dst, struct dhcp6_list *src)
+{
 	struct dhcp6_listval *ent;
 
 	for (ent = TAILQ_FIRST(src); ent; ent = TAILQ_NEXT(ent, link)) {
@@ -134,8 +230,7 @@ dhcp6_copy_list(dst, src)
 }
 
 void
-dhcp6_move_list(dst, src)
-	struct dhcp6_list *dst, *src;
+dhcp6_move_list(struct dhcp6_list *dst, struct dhcp6_list *src)
 {
 	struct dhcp6_listval *v;
 
@@ -146,8 +241,7 @@ dhcp6_move_list(dst, src)
 }
 
 void
-dhcp6_clear_list(head)
-	struct dhcp6_list *head;
+dhcp6_clear_list(struct dhcp6_list *head)
 {
 	struct dhcp6_listval *v;
 
@@ -160,8 +254,7 @@ dhcp6_clear_list(head)
 }
 
 static int
-dhcp6_count_list(head)
-	struct dhcp6_list *head;
+dhcp6_count_list(struct dhcp6_list *head)
 {
 	struct dhcp6_listval *v;
 	int i;
@@ -173,8 +266,7 @@ dhcp6_count_list(head)
 }
 
 void
-dhcp6_clear_listval(lv)
-	struct dhcp6_listval *lv;
+dhcp6_clear_listval(struct dhcp6_listval *lv)
 {
 	dhcp6_clear_list(&lv->sublist);
 	switch (lv->type) {
@@ -192,11 +284,8 @@ dhcp6_clear_listval(lv)
  * VAL.  It also does not care about sublists.
  */
 struct dhcp6_listval *
-dhcp6_find_listval(head, type, val, option)
-	struct dhcp6_list *head;
-	dhcp6_listval_type_t type;
-	void *val;
-	int option;
+dhcp6_find_listval(struct dhcp6_list *head, dhcp6_listval_type_t type,
+    void *val, int option)
 {
 	struct dhcp6_listval *lv;
 
@@ -210,7 +299,7 @@ dhcp6_find_listval(head, type, val, option)
 				return (lv);
 			break;
 		case DHCP6_LISTVAL_STCODE:
-			if (lv->val_num16 == *(u_int16_t *)val)
+			if (lv->val_num16 == *(uint16_t *)val)
 				return (lv);
 			break;
 		case DHCP6_LISTVAL_ADDR6:
@@ -257,10 +346,8 @@ dhcp6_find_listval(head, type, val, option)
 }
 
 struct dhcp6_listval *
-dhcp6_add_listval(head, type, val, sublist)
-	struct dhcp6_list *head, *sublist;
-	dhcp6_listval_type_t type;
-	void *val;
+dhcp6_add_listval(struct dhcp6_list *head, dhcp6_listval_type_t type,
+    void *val, struct dhcp6_list *sublist)
 {
 	struct dhcp6_listval *lv = NULL;
 
@@ -278,7 +365,7 @@ dhcp6_add_listval(head, type, val, sublist)
 		lv->val_num = *(int *)val;
 		break;
 	case DHCP6_LISTVAL_STCODE:
-		lv->val_num16 = *(u_int16_t *)val;
+		lv->val_num16 = *(uint16_t *)val;
 		break;
 	case DHCP6_LISTVAL_ADDR6:
 		lv->val_addr6 = *(struct in6_addr *)val;
@@ -318,8 +405,7 @@ dhcp6_add_listval(head, type, val, sublist)
 }
 
 int
-dhcp6_vbuf_copy(dst, src)
-	struct dhcp6_vbuf *dst, *src;
+dhcp6_vbuf_copy(struct dhcp6_vbuf *dst, struct dhcp6_vbuf *src)
 {
 	dst->dv_buf = malloc(src->dv_len);
 	if (dst->dv_buf == NULL)
@@ -332,8 +418,7 @@ dhcp6_vbuf_copy(dst, src)
 }
 
 void
-dhcp6_vbuf_free(vbuf)
-	struct dhcp6_vbuf *vbuf;
+dhcp6_vbuf_free(struct dhcp6_vbuf *vbuf)
 {
 	free(vbuf->dv_buf);
 
@@ -342,8 +427,7 @@ dhcp6_vbuf_free(vbuf)
 }
 
 int
-dhcp6_vbuf_cmp(vb1, vb2)
-	struct dhcp6_vbuf *vb1, *vb2;
+dhcp6_vbuf_cmp(struct dhcp6_vbuf *vb1, struct dhcp6_vbuf *vb2)
 {
 	if (vb1->dv_len != vb2->dv_len)
 		return (vb1->dv_len - vb2->dv_len);
@@ -352,20 +436,18 @@ dhcp6_vbuf_cmp(vb1, vb2)
 }
 
 static int
-dhcp6_get_addr(optlen, cp, type, list)
-	int optlen;
-	void *cp;
-	dhcp6_listval_type_t type;
-	struct dhcp6_list *list;
+dhcp6_get_addr(int optlen, void *cp, dhcp6_listval_type_t type,
+    struct dhcp6_list *list)
 {
-	void *val;
+	char *val;
 
 	if (optlen % sizeof(struct in6_addr) || optlen == 0) {
 		d_printf(LOG_INFO, FNAME,
 		    "malformed DHCP option: type %d, len %d", type, optlen);
 		return -1;
 	}
-	for (val = cp; val < cp + optlen; val += sizeof(struct in6_addr)) {
+	for (val = (char *)cp; val < (char *)cp + optlen;
+	    val += sizeof(struct in6_addr)) {
 		struct in6_addr valaddr;
 
 		memcpy(&valaddr, val, sizeof(valaddr));
@@ -395,30 +477,27 @@ dhcp6_set_addr(type, list, p, optep, len)
 	int *len;
 {
 	struct in6_addr *in6;
-	char *tmpbuf;
 	struct dhcp6_listval *d;
 	int optlen;
 
 	if (TAILQ_EMPTY(list))
 		return 0;
 
-	tmpbuf = NULL;
 	optlen = dhcp6_count_list(list) * sizeof(struct in6_addr);
-	if ((tmpbuf = malloc(optlen)) == NULL) {
+	if ((in6 = malloc(optlen)) == NULL) {
 		d_printf(LOG_ERR, FNAME,
 		    "memory allocation failed for %s options",
 		    dhcp6optstr(type));
 		return -1;
 	}
-	in6 = (struct in6_addr *)tmpbuf;
 	for (d = TAILQ_FIRST(list); d; d = TAILQ_NEXT(d, link), in6++)
 		memcpy(in6, &d->val_addr6, sizeof(*in6));
-	if (copy_option(type, optlen, tmpbuf, p, optep, len) != 0) {
-		free(tmpbuf);
+	if (copy_option(type, optlen, (char *)in6, p, optep, len) != 0) {
+		free(in6);
 		return -1;
 	}
 
-	free(tmpbuf);
+	free(in6);
 	return 0;
 }
 
@@ -429,15 +508,15 @@ dhcp6_get_domain(optlen, cp, type, list)
 	dhcp6_listval_type_t type;
 	struct dhcp6_list *list;
 {
-	void *val;
+	char *val;
 
-	val = cp;
-	while (val < cp + optlen) {
+	val = (char *)cp;
+	while (val < (char *)cp + optlen) {
 		struct dhcp6_vbuf vb;
 		char name[MAXDNAME + 1];
 
 		if (dnsdecode((u_char **)(void *)&val,
-		    (u_char *)(cp + optlen), name, sizeof(name)) == NULL) {
+		    (u_char *)((char *)cp + optlen), name, sizeof(name)) == NULL) {
 			d_printf(LOG_INFO, FNAME, "failed to "
 			    "decode a %s domain name",
 			    dhcp6optstr(type));
@@ -630,11 +709,11 @@ copy_authparam(authparam)
  * XXX: is there any standard for this?
  */
 #if (BYTE_ORDER == LITTLE_ENDIAN)
-static __inline u_int64_t
-ntohq(u_int64_t x)
+static __inline uint64_t
+ntohq(uint64_t x)
 {
-	return (u_int64_t)ntohl((u_int32_t)(x >> 32)) |
-	    (int64_t)ntohl((u_int32_t)(x & 0xffffffff)) << 32;
+	return (uint64_t)ntohl((uint32_t)(x >> 32)) |
+	    (int64_t)ntohl((uint32_t)(x & 0xffffffff)) << 32;
 }
 #else	/* (BYTE_ORDER == LITTLE_ENDIAN) */
 #define ntohq(x) (x)
@@ -643,7 +722,7 @@ ntohq(u_int64_t x)
 int
 dhcp6_auth_replaycheck(method, prev, current)
 	int method;
-	u_int64_t prev, current;
+	uint64_t prev, current;
 {
 	char bufprev[] = "ffff ffff ffff ffff";
 	char bufcurrent[] = "ffff ffff ffff ffff";
@@ -733,7 +812,7 @@ getifaddr(addr, ifnam, prefix, plen, strong, ignorefla
 			memset(&m, 0, sizeof(m));
 			memset(&m, 0xff, plen / 8);
 			m.s6_addr[plen / 8] = (0xff00 >> (plen % 8)) & 0xff;
-			for (i = 0; i < sizeof(a); i++)
+			for (i = 0; i < (int)sizeof(a); i++)
 				a.s6_addr[i] &= m.s6_addr[i];
 
 			if (memcmp(&a, prefix, plen / 8) != 0 ||
@@ -774,7 +853,7 @@ getifidfromaddr(addr, ifidp)
 		if (ifa->ifa_addr->sa_family != AF_INET6)
 			continue;
 
-		sa6 = (struct sockaddr_in6 *)ifa->ifa_addr;
+		sa6 = (struct sockaddr_in6 *)(void *)ifa->ifa_addr;
 		if (IN6_ARE_ADDR_EQUAL(addr, &sa6->sin6_addr))
 			break;
 	}
@@ -823,11 +902,11 @@ transmit_sa(s, sa, buf, len)
 	char *buf;
 	size_t len;
 {
-	int error;
+	ssize_t error;
 
 	error = sendto(s, buf, len, 0, sa, sysdep_sa_len(sa));
 
-	return (error != len) ? -1 : 0;
+	return (error != (ssize_t)len) ? -1 : 0;
 }
 
 long
@@ -971,7 +1050,7 @@ in6_matchflags(addr, ifnam, flags)
 	}
 	memset(&ifr6, 0, sizeof(ifr6));
 	strncpy(ifr6.ifr_name, ifnam, sizeof(ifr6.ifr_name));
-	ifr6.ifr_addr = *(struct sockaddr_in6 *)addr;
+	ifr6.ifr_addr = *(struct sockaddr_in6 *)(void *)addr;
 
 	if (ioctl(s, SIOCGIFAFLAG_IN6, &ifr6) < 0) {
 		warn("in6_matchflags: ioctl(SIOCGIFAFLAG_IN6, %s)",
@@ -990,11 +1069,11 @@ in6_matchflags(addr, ifnam, flags)
 
 int
 get_duid(idfile, duid)
-	char *idfile;
+	const char *idfile;
 	struct duid *duid;
 {
 	FILE *fp = NULL;
-	u_int16_t len = 0, hwtype;
+	uint16_t len = 0, hwtype;
 	struct dhcp6opt_duid_type1 *dp; /* we only support the type1 DUID */
 	char tmpbuf[256];	/* DUID should be no more than 256 bytes */
 
@@ -1037,13 +1116,13 @@ get_duid(idfile, duid)
 		    "extracted an existing DUID from %s: %s",
 		    idfile, duidstr(duid));
 	} else {
-		u_int64_t t64;
+		uint64_t t64;
 
 		dp = (struct dhcp6opt_duid_type1 *)duid->duid_id;
 		dp->dh6_duid1_type = htons(1); /* type 1 */
 		dp->dh6_duid1_hwtype = htons(hwtype);
 		/* time is Jan 1, 2000 (UTC), modulo 2^32 */
-		t64 = (u_int64_t)(time(NULL) - 946684800);
+		t64 = (uint64_t)(time(NULL) - 946684800);
 		dp->dh6_duid1_time = htonl((u_long)(t64 & 0xffffffff));
 		memcpy((void *)(dp + 1), tmpbuf, (len - sizeof(*dp)));
 
@@ -1055,7 +1134,7 @@ get_duid(idfile, duid)
 	if (!fp) {
 		if ((fp = fopen(idfile, "w+")) == NULL) {
 			d_printf(LOG_ERR, FNAME,
-			    "failed to open DUID file for save");
+			    "failed to open DUID file %s for save", idfile);
 			goto fail;
 		}
 		if ((fwrite(&len, sizeof(len), 1, fp)) != 1) {
@@ -1088,12 +1167,12 @@ get_duid(idfile, duid)
 #ifdef __sun__
 struct hwparms {
 	char *buf;
-	u_int16_t *hwtypep;
+	uint16_t *hwtypep;
 	ssize_t retval;
 };
 
 static ssize_t
-getifhwaddr(const char *ifname, char *buf, u_int16_t *hwtypep, int ppa)
+getifhwaddr(const char *ifname, char *buf, uint16_t *hwtypep, int ppa)
 {
 	int fd, flags;
 	char fname[MAXPATHLEN], *cp;
@@ -1221,7 +1300,7 @@ gethwid(buf, len, ifname, hwtypep)
 	char *buf;
 	int len;
 	const char *ifname;
-	u_int16_t *hwtypep;
+	uint16_t *hwtypep;
 {
 	struct ifaddrs *ifa, *ifap;
 #ifdef __KAME__
@@ -1266,7 +1345,7 @@ gethwid(buf, len, ifname, hwtypep)
 		if (ifa->ifa_addr->sa_family != AF_LINK)
 			continue;
 
-		sdl = (struct sockaddr_dl *)ifa->ifa_addr;
+		sdl = (struct sockaddr_dl *)(void *)ifa->ifa_addr;
 		if (len < 2 + sdl->sdl_alen)
 			goto fail;
 		/*
@@ -1336,6 +1415,7 @@ dhcp6_init_options(optinfo)
 	TAILQ_INIT(&optinfo->nispname_list);
 	TAILQ_INIT(&optinfo->bcmcs_list);
 	TAILQ_INIT(&optinfo->bcmcsname_list);
+	TAILQ_INIT(&optinfo->rawops);
 
 	optinfo->authproto = DHCP6_AUTHPROTO_UNDEF;
 	optinfo->authalgorithm = DHCP6_AUTHALG_UNDEF;
@@ -1380,6 +1460,8 @@ dhcp6_clear_options(optinfo)
 	if (optinfo->ifidopt_id != NULL)
 		free(optinfo->ifidopt_id);
 
+	rawop_clear_list(&optinfo->rawops);
+
 	dhcp6_init_options(optinfo);
 }
 
@@ -1429,6 +1511,8 @@ dhcp6_copy_options(dst, src)
 	dst->refreshtime = src->refreshtime;
 	dst->pref = src->pref;
 
+	rawop_copy_list(&dst->rawops, &src->rawops);
+
 	if (src->relaymsg_msg != NULL) {
 		if ((dst->relaymsg_msg = malloc(src->relaymsg_len)) == NULL)
 			goto fail;
@@ -1488,10 +1572,10 @@ dhcp6_get_options(p, ep, optinfo)
 {
 	struct dhcp6opt *np, opth;
 	int i, opt, optlen, reqopts, num;
-	u_int16_t num16;
+	uint16_t num16;
 	char *bp, *cp, *val;
-	u_int16_t val16;
-	u_int32_t val32;
+	uint16_t val16;
+	uint32_t val32;
 	struct dhcp6opt_ia optia;
 	struct dhcp6_ia ia;
 	struct dhcp6_list sublist;
@@ -1546,7 +1630,7 @@ dhcp6_get_options(p, ep, optinfo)
 			}
 			break;
 		case DH6OPT_STATUS_CODE:
-			if (optlen < sizeof(u_int16_t))
+			if (optlen < (int)sizeof(uint16_t))
 				goto malformed;
 			memcpy(&val16, cp, sizeof(val16));
 			num16 = ntohs(val16);
@@ -1568,10 +1652,10 @@ dhcp6_get_options(p, ep, optinfo)
 				goto malformed;
 			reqopts = optlen / 2;
 			for (i = 0, val = cp; i < reqopts;
-			     i++, val += sizeof(u_int16_t)) {
-				u_int16_t opttype;
+			     i++, val += sizeof(uint16_t)) {
+				uint16_t opttype;
 
-				memcpy(&opttype, val, sizeof(u_int16_t));
+				memcpy(&opttype, val, sizeof(uint16_t));
 				num = (int)ntohs(opttype);
 
 				d_printf(LOG_DEBUG, "",
@@ -1613,7 +1697,7 @@ dhcp6_get_options(p, ep, optinfo)
 			memcpy(&val16, cp, sizeof(val16));
 			val16 = ntohs(val16);
 			d_printf(LOG_DEBUG, "", "  elapsed time: %lu",
-			    (u_int32_t)val16);
+			    (uint32_t)val16);
 			if (optinfo->elapsed_time !=
 			    DH6OPT_ELAPSED_TIME_UNDEF) {
 				d_printf(LOG_INFO, FNAME,
@@ -1628,7 +1712,7 @@ dhcp6_get_options(p, ep, optinfo)
 			optinfo->relaymsg_len = optlen;
 			break;
 		case DH6OPT_AUTH:
-			if (optlen < sizeof(struct dhcp6opt_auth) - 4)
+			if (optlen < (int)sizeof(struct dhcp6opt_auth) - 4)
 				goto malformed;
 
 			/*
@@ -1661,7 +1745,7 @@ dhcp6_get_options(p, ep, optinfo)
 				}
 				/* XXX: should we reject an empty realm? */
 				if (authinfolen <
-				    sizeof(optinfo->delayedauth_keyid) + 16) {
+				    (int)sizeof(optinfo->delayedauth_keyid) + 16) {
 					goto malformed;
 				}
 
@@ -1697,6 +1781,15 @@ dhcp6_get_options(p, ep, optinfo)
 			case DHCP6_AUTHPROTO_RECONFIG:
 				break;
 #endif
+			/* XXX */
+			case 0:
+				// Discard auth
+				d_printf(LOG_DEBUG, FNAME, "  Discarding null authentication");
+				optinfo->authproto = DHCP6_AUTHPROTO_UNDEF;
+				optinfo->authalgorithm = DHCP6_AUTHALG_UNDEF;
+				optinfo->authrdm = DHCP6_AUTHRDM_UNDEF;
+				break;
+
 			default:
 				d_printf(LOG_INFO, FNAME,
 				    "unsupported authentication protocol: %d",
@@ -1872,6 +1965,7 @@ dhcp6_get_options(p, ep, optinfo)
 			dhcp6_clear_list(&sublist);
 
 			break;
+
 		default:
 			/* no option specific behavior */
 			d_printf(LOG_INFO, FNAME,
@@ -1927,7 +2021,7 @@ dnsdecode(sp, ep, buf, bufsiz)
 			if (!isprint(*cp)) /* we don't accept non-printables */
 				return (NULL);
 			l = snprintf(tmpbuf, sizeof(tmpbuf), "%c" , *cp);
-			if (l >= sizeof(tmpbuf) || l < 0)
+			if (l >= (int)sizeof(tmpbuf) || l < 0)
 				return (NULL);
 			if (strlcat(buf, tmpbuf, bufsiz) >= bufsiz)
 				return (NULL); /* result overrun */
@@ -2144,10 +2238,10 @@ static char *
 sprint_uint64(buf, buflen, i64)
 	char *buf;
 	int buflen;
-	u_int64_t i64;
+	uint64_t i64;
 {
-	u_int16_t rd0, rd1, rd2, rd3;
-	u_int16_t *ptr = (u_int16_t *)(void *)&i64;
+	uint16_t rd0, rd1, rd2, rd3;
+	uint16_t *ptr = (uint16_t *)(void *)&i64;
 
 	rd0 = ntohs(*ptr++);
 	rd1 = ntohs(*ptr++);
@@ -2164,9 +2258,12 @@ sprint_auth(optinfo)
 	struct dhcp6_optinfo *optinfo;
 {
 	static char ret[1024];	/* XXX: thread unsafe */
-	char *proto, proto0[] = "unknown(255)";
-	char *alg, alg0[] = "unknown(255)";
-	char *rdm, rdm0[] = "unknown(255)";
+	const char *proto;
+	char proto0[] = "unknown(255)";
+	const char *alg;
+	char alg0[] = "unknown(255)";
+	const char *rdm;
+	char rdm0[] = "unknown(255)";
 	char rd[] = "ffff ffff ffff ffff";
 
 	switch (optinfo->authproto) {
@@ -2212,15 +2309,12 @@ sprint_auth(optinfo)
 }
 
 static int
-copy_option(type, len, val, optp, ep, totallenp)
-	u_int16_t type, len;
-	void *val;
-	struct dhcp6opt **optp, *ep;
-	int *totallenp;
+copy_option(uint16_t type, uint16_t len, void *val,
+    struct dhcp6opt **optp, struct dhcp6opt *ep, int *totallenp)
 {
 	struct dhcp6opt *opt = *optp, opth;
 
-	if ((void *)ep - (void *)optp < len + sizeof(struct dhcp6opt)) {
+	if ((char *)ep - (char *)optp < (intptr_t)(len + sizeof(struct dhcp6opt))) {
 		d_printf(LOG_INFO, FNAME,
 		    "option buffer short for %s", dhcp6optstr(type));
 		return (-1);
@@ -2248,6 +2342,7 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 	struct dhcp6_listval *stcode, *op;
 	int len = 0, optlen;
 	char *tmpbuf = NULL;
+	struct rawoption *rawop;
 
 	if (optinfo->clientID.duid_len) {
 		if (copy_option(DH6OPT_CLIENTID, optinfo->clientID.duid_len,
@@ -2265,15 +2360,13 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 
 	for (op = TAILQ_FIRST(&optinfo->iana_list); op;
 	    op = TAILQ_NEXT(op, link)) {
-		int optlen;
-
 		tmpbuf = NULL;
 		if ((optlen = copyout_option(NULL, NULL, op)) < 0) {
 			d_printf(LOG_INFO, FNAME,
 			    "failed to count option length");
 			goto fail;
 		}
-		if ((void *)optep - (void *)p < optlen) {
+		if ((char *)optep - (char *)p < (intptr_t)optlen) {
 			d_printf(LOG_INFO, FNAME, "short buffer");
 			goto fail;
 		}
@@ -2302,7 +2395,7 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 	}
 
 	if (optinfo->pref != DH6OPT_PREF_UNDEF) {
-		u_int8_t p8 = (u_int8_t)optinfo->pref;
+		uint8_t p8 = (uint8_t)optinfo->pref;
 
 		if (copy_option(DH6OPT_PREFERENCE, sizeof(p8), &p8, &p,
 		    optep, &len) != 0) {
@@ -2311,7 +2404,7 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 	}
 
 	if (optinfo->elapsed_time != DH6OPT_ELAPSED_TIME_UNDEF) {
-		u_int16_t p16 = (u_int16_t)optinfo->elapsed_time;
+		uint16_t p16 = (uint16_t)optinfo->elapsed_time;
 
 		p16 = htons(p16);
 		if (copy_option(DH6OPT_ELAPSED_TIME, sizeof(p16), &p16, &p,
@@ -2322,7 +2415,7 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 
 	for (stcode = TAILQ_FIRST(&optinfo->stcode_list); stcode;
 	     stcode = TAILQ_NEXT(stcode, link)) {
-		u_int16_t code;
+		uint16_t code;
 
 		code = htons(stcode->val_num16);
 		if (copy_option(DH6OPT_STATUS_CODE, sizeof(code), &code, &p,
@@ -2333,19 +2426,17 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 
 	if (!TAILQ_EMPTY(&optinfo->reqopt_list)) {
 		struct dhcp6_listval *opt;
-		u_int16_t *valp;
+		uint16_t *valp, *valp0;
 		int buflen;
 
-		tmpbuf = NULL;
 		buflen = dhcp6_count_list(&optinfo->reqopt_list) *
-			sizeof(u_int16_t);
-		if ((tmpbuf = malloc(buflen)) == NULL) {
+			sizeof(uint16_t);
+		if ((valp0 = valp = malloc(buflen)) == NULL) {
 			d_printf(LOG_ERR, FNAME,
 			    "memory allocation failed for options");
 			goto fail;
 		}
 		optlen = 0;
-		valp = (u_int16_t *)tmpbuf;
 		for (opt = TAILQ_FIRST(&optinfo->reqopt_list); opt;
 		     opt = TAILQ_NEXT(opt, link)) {
 			/*
@@ -2360,17 +2451,16 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 				    "for %s", dhcp6msgstr(type));
 			}
 
-			*valp = htons((u_int16_t)opt->val_num);
+			*valp = htons((uint16_t)opt->val_num);
 			valp++;
-			optlen += sizeof(u_int16_t);
+			optlen += sizeof(uint16_t);
 		}
 		if (optlen > 0 &&
-		    copy_option(DH6OPT_ORO, optlen, tmpbuf, &p,
+		    copy_option(DH6OPT_ORO, optlen, (void *)valp0, &p,
 		    optep, &len) != 0) {
 			goto fail;
 		}
-		free(tmpbuf);
-		tmpbuf = NULL;
+		free(valp0);
 	}
 
 	if (dhcp6_set_domain(DH6OPT_SIP_SERVER_D, &optinfo->sipname_list,
@@ -2419,15 +2509,13 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 
 	for (op = TAILQ_FIRST(&optinfo->iapd_list); op;
 	    op = TAILQ_NEXT(op, link)) {
-		int optlen;
-
 		tmpbuf = NULL;
 		if ((optlen = copyout_option(NULL, NULL, op)) < 0) {
 			d_printf(LOG_INFO, FNAME,
 			    "failed to count option length");
 			goto fail;
 		}
-		if ((void *)optep - (void *)p < optlen) {
+		if ((char *)optep - (char *)p < (intptr_t)optlen) {
 			d_printf(LOG_INFO, FNAME, "short buffer");
 			goto fail;
 		}
@@ -2463,7 +2551,7 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 	}
 
 	if (optinfo->refreshtime != DH6OPT_REFRESHTIME_UNDEF) {
-		u_int32_t p32 = (u_int32_t)optinfo->refreshtime;
+		uint32_t p32 = (uint32_t)optinfo->refreshtime;
 
 		p32 = htonl(p32);
 		if (copy_option(DH6OPT_REFRESHTIME, sizeof(p32), &p32, &p,
@@ -2472,6 +2560,20 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 		}
 	}
 
+	for (rawop = TAILQ_FIRST(&optinfo->rawops); rawop;
+	    rawop = TAILQ_NEXT(rawop, link)) {
+
+		d_printf(LOG_DEBUG, FNAME,
+			"  raw option %d length %d at %p",
+			rawop->opnum, rawop->datalen, (void*)rawop);
+
+		if (copy_option(rawop->opnum, rawop->datalen,
+			rawop->data, &p,
+		    optep, &len) != 0) {
+			goto fail;
+		}
+	}
+
 	if (optinfo->authproto != DHCP6_AUTHPROTO_UNDEF) {
 		struct dhcp6opt_auth *auth;
 		int authlen;
@@ -2505,14 +2607,14 @@ dhcp6_set_options(type, optbp, optep, optinfo)
 
 		memset(auth, 0, authlen);
 		/* copy_option will take care of type and len later */
-		auth->dh6_auth_proto = (u_int8_t)optinfo->authproto;
-		auth->dh6_auth_alg = (u_int8_t)optinfo->authalgorithm;
-		auth->dh6_auth_rdm = (u_int8_t)optinfo->authrdm;
+		auth->dh6_auth_proto = (uint8_t)optinfo->authproto;
+		auth->dh6_auth_alg = (uint8_t)optinfo->authalgorithm;
+		auth->dh6_auth_rdm = (uint8_t)optinfo->authrdm;
 		memcpy(auth->dh6_auth_rdinfo, &optinfo->authrd,
 		    sizeof(auth->dh6_auth_rdinfo));
 
 		if (!(optinfo->authflags & DHCP6OPT_AUTHFLAG_NOINFO)) {
-			u_int32_t p32;
+			uint32_t p32;
 
 			switch (optinfo->authproto) {
 			case DHCP6_AUTHPROTO_DELAYED:
@@ -2823,7 +2925,7 @@ dhcp6_reset_timer(ev)
 	struct dhcp6_event *ev;
 {
 	double n, r;
-	char *statestr;
+	const char *statestr;
 	struct timeval interval;
 
 	switch(ev->state) {
@@ -2926,13 +3028,13 @@ get_rdvalue(rdm, rdvalue, rdsize)
 #else
 	struct timeval tv;
 #endif
-	u_int32_t u32, l32;
+	uint32_t u32, l32;
 
 	if (rdm != DHCP6_AUTHRDM_MONOCOUNTER) {
 		d_printf(LOG_INFO, FNAME, "unsupported RDM (%d)", rdm);
 		return (-1);
 	}
-	if (rdsize != sizeof(u_int64_t)) {
+	if (rdsize != sizeof(uint64_t)) {
 		d_printf(LOG_INFO, FNAME, "unsupported RD size (%d)", rdsize);
 		return (-1);
 	}
@@ -2944,21 +3046,21 @@ get_rdvalue(rdm, rdvalue, rdsize)
 		return (-1);
 	}
 
-	u32 = (u_int32_t)tp.tv_sec;
+	u32 = (uint32_t)tp.tv_sec;
 	u32 += JAN_1970;
 
 	nsec = (double)tp.tv_nsec / 1e9 * 0x100000000ULL;
 	/* nsec should be smaller than 2^32 */
-	l32 = (u_int32_t)nsec;
+	l32 = (uint32_t)nsec;
 #else
 	if (gettimeofday(&tv, NULL) != 0) {
 		d_printf(LOG_WARNING, FNAME, "gettimeofday failed: %s",
 		    strerror(errno));
 		return (-1);
 	}
-	u32 = (u_int32_t)tv.tv_sec;
+	u32 = (uint32_t)tv.tv_sec;
 	u32 += JAN_1970;
-	l32 = (u_int32_t)tv.tv_usec;
+	l32 = (uint32_t)tv.tv_usec;
 #endif /* HAVE_CLOCK_GETTIME */
 
 	u32 = htonl(u32);
@@ -2970,7 +3072,7 @@ get_rdvalue(rdm, rdvalue, rdsize)
 	return (0);
 }
 
-char *
+const char *
 dhcp6optstr(type)
 	int type;
 {
@@ -3052,13 +3154,17 @@ dhcp6optstr(type)
 		return ("subscriber ID");
 	case DH6OPT_CLIENT_FQDN:
 		return ("client FQDN");
+/*	Either a known or an unknown option. RAW is a syntax, not an option
+	case DHCPOPT_RAW:
+		return ("raw");
+*/
 	default:
 		snprintf(genstr, sizeof(genstr), "opt_%d", type);
 		return (genstr);
 	}
 }
 
-char *
+const char *
 dhcp6msgstr(type)
 	int type;
 {
@@ -3100,9 +3206,8 @@ dhcp6msgstr(type)
 	}
 }
 
-char *
-dhcp6_stcodestr(code)
-	u_int16_t code;
+const char *
+dhcp6_stcodestr(uint16_t code)
 {
 	static char genstr[sizeof("code255") + 1]; /* XXX thread unsafe */
 
@@ -3134,7 +3239,8 @@ char *
 duidstr(duid)
 	struct duid *duid;
 {
-	int i, n;
+	size_t i;
+	int n;
 	char *cp, *ep;
 	static char duidstr[sizeof("xx:") * 128 + sizeof("...")];
 
@@ -3153,7 +3259,7 @@ duidstr(duid)
 	return (duidstr);
 }
 
-char *dhcp6_event_statestr(ev)
+const char *dhcp6_event_statestr(ev)
 	struct dhcp6_event *ev;
 {
 	switch(ev->state) {
@@ -3200,12 +3306,15 @@ setloglevel(debuglevel)
 		switch(debuglevel) {
 		case 0:
 			setlogmask(LOG_UPTO(LOG_ERR));
+			debug_thresh = LOG_ERR;
 			break;
 		case 1:
 			setlogmask(LOG_UPTO(LOG_INFO));
+			debug_thresh = LOG_INFO;
 			break;
 		case 2:
 			setlogmask(LOG_UPTO(LOG_DEBUG));
+			debug_thresh = LOG_DEBUG;
 			break;
 		}
 	}
@@ -3241,8 +3350,16 @@ d_printf(int level, const char *fname, const char *fmt
 		    tm_now->tm_hour, tm_now->tm_min, tm_now->tm_sec,
 		    fname, printfname ? ": " : "",
 		    logbuf);
-	} else
+	} else {
+		/*
+		 * XXX DEBUG/INFO require NOTICE in order to
+		 * to appear in the OPNsense system log file.
+                 */
+		if (debug_thresh <= level && level > LOG_NOTICE) {
+			level = LOG_NOTICE;
+		}
 		syslog(level, "%s%s%s", fname, printfname ? ": " : "", logbuf);
+	}
 }
 
 int
@@ -3265,7 +3382,7 @@ ifaddrconf(cmd, ifname, addr, plen, pltime, vltime)
 	struct lifreq req;
 #endif
 	unsigned long ioctl_cmd;
-	char *cmdstr;
+	const char *cmdstr;
 	int s;			/* XXX overhead */
 
 	switch(cmd) {
@@ -3388,6 +3505,42 @@ safefile(path)
 		    path, (s.st_mode & S_IFMT));
 		return (-1);
 	}
+
+	return (0);
+}
+
+int
+get_val32(char **bpp, int *lenp, uint32_t *valp)
+{
+	char *bp = *bpp;
+	size_t len = (size_t)*lenp;
+	uint32_t i32;
+
+	if (len < sizeof(*valp))
+		return (-1);
+
+	memcpy(&i32, bp, sizeof(i32));
+	*valp = ntohl(i32);
+
+	*bpp = bp + sizeof(*valp);
+	*lenp = len - sizeof(*valp);
+
+	return (0);
+}
+
+int
+get_val(char **bpp, int *lenp, void *valp, size_t vallen)
+{
+	char *bp = *bpp;
+	size_t len = (size_t)*lenp;
+
+	if (len < vallen)
+		return (-1);
+
+	memcpy(valp, bp, vallen);
+
+	*bpp = bp + vallen;
+	*lenp = len - vallen;
 
 	return (0);
 }
