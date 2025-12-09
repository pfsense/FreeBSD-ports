--- cfparse.y.orig	2017-02-28 19:06:15 UTC
+++ cfparse.y
@@ -41,16 +41,22 @@
 #include <stdlib.h>
 #include <string.h>
 
+/* XXX */
+#include <stdio.h>
+#include <ctype.h>
+
 #include "dhcp6.h"
 #include "config.h"
 #include "common.h"
 
 extern int lineno;
 extern int cfdebug;
+extern int yychar;
+extern int yynerrs;
 
-extern void yywarn __P((char *, ...))
+void yywarn(const char *, ...)
 	__attribute__((__format__(__printf__, 1, 2)));
-extern void yyerror __P((char *, ...))
+void yyerror(const char *, ...)
 	__attribute__((__format__(__printf__, 1, 2)));
 
 #define MAKE_NAMELIST(l, n, p) do { \
@@ -86,19 +92,26 @@ static struct cf_namelist *iflist_head, *hostlist_head
 static struct cf_namelist *addrpoollist_head;
 static struct cf_namelist *authinfolist_head, *keylist_head;
 static struct cf_namelist *ianalist_head;
+
 struct cf_list *cf_dns_list, *cf_dns_name_list, *cf_ntp_list;
 struct cf_list *cf_sip_list, *cf_sip_name_list;
 struct cf_list *cf_nis_list, *cf_nis_name_list;
 struct cf_list *cf_nisp_list, *cf_nisp_name_list;
 struct cf_list *cf_bcmcs_list, *cf_bcmcs_name_list;
+
+extern long long cf_refreshtime;
 long long cf_refreshtime = -1;
 
-extern int yylex __P((void));
-extern int cfswitch_buffer __P((char *));
-static int add_namelist __P((struct cf_namelist *, struct cf_namelist **));
-static void cleanup __P((void));
-static void cleanup_namelist __P((struct cf_namelist *));
-static void cleanup_cflist __P((struct cf_list *));
+int yylex(void);
+int cfswitch_buffer(char *);
+
+struct rawoption *make_rawoption(int opnum, char *datastr);
+static int add_namelist(struct cf_namelist *, struct cf_namelist **);
+static void cleanup(void);
+static void cleanup_namelist(struct cf_namelist *);
+static void cleanup_cflist(struct cf_list *);
+int cf_post_config(void);
+void cf_init(void);
 %}
 
 %token INTERFACE IFNAME
@@ -122,6 +135,7 @@ static void cleanup_cflist __P((struct cf_list *));
 
 %token NUMBER SLASH EOS BCL ECL STRING QSTRING PREFIX INFINITY
 %token COMMA
+%token RAW
 
 %union {
 	long long num;
@@ -163,7 +177,7 @@ statement:
 	;
 
 interface_statement:
-	INTERFACE IFNAME BCL declarations ECL EOS 
+	INTERFACE IFNAME BCL declarations ECL EOS
 	{
 		struct cf_namelist *ifl;
 
@@ -500,7 +514,7 @@ declarations:
 			$$ = head;
 		}
 	;
-	
+
 declaration:
 		SEND dhcpoption_list EOS
 		{
@@ -664,6 +678,15 @@ dhcpoption:
 			/* currently no value */
 			$$ = l;
 		}
+	|	RAW NUMBER STRING
+		{
+			struct cf_list *l;
+			struct rawoption *rawoption = make_rawoption ($2, $3);
+
+			MAKE_CFLIST(l, DHCPOPT_RAW, NULL, NULL);
+			l->ptr = rawoption;
+			$$ = l;
+		}
 	|	DNS_SERVERS
 		{
 			struct cf_list *l;
@@ -749,7 +772,7 @@ dhcpoption:
 rangeparam:
 		STRING TO STRING
 		{
-			struct dhcp6_range range0, *range;		
+			struct dhcp6_range range0, *range;
 
 			memset(&range0, 0, sizeof(range0));
 			if (inet_pton(AF_INET6, $1, &range0.min) != 1) {
@@ -780,7 +803,7 @@ rangeparam:
 addressparam:
 		STRING duration
 		{
-			struct dhcp6_prefix pconf0, *pconf;		
+			struct dhcp6_prefix pconf0, *pconf;
 
 			memset(&pconf0, 0, sizeof(pconf0));
 			if (inet_pton(AF_INET6, $1, &pconf0.addr) != 1) {
@@ -794,7 +817,7 @@ addressparam:
 			if ($2 < 0)
 				pconf0.pltime = DHCP6_DURATION_INFINITE;
 			else
-				pconf0.pltime = (u_int32_t)$2;
+				pconf0.pltime = (uint32_t)$2;
 			pconf0.vltime = pconf0.pltime;
 
 			if ((pconf = malloc(sizeof(*pconf))) == NULL) {
@@ -807,7 +830,7 @@ addressparam:
 		}
 	|	STRING duration duration
 		{
-			struct dhcp6_prefix pconf0, *pconf;		
+			struct dhcp6_prefix pconf0, *pconf;
 
 			memset(&pconf0, 0, sizeof(pconf0));
 			if (inet_pton(AF_INET6, $1, &pconf0.addr) != 1) {
@@ -821,11 +844,11 @@ addressparam:
 			if ($2 < 0)
 				pconf0.pltime = DHCP6_DURATION_INFINITE;
 			else
-				pconf0.pltime = (u_int32_t)$2;
+				pconf0.pltime = (uint32_t)$2;
 			if ($3 < 0)
 				pconf0.vltime = DHCP6_DURATION_INFINITE;
 			else
-				pconf0.vltime = (u_int32_t)$3;
+				pconf0.vltime = (uint32_t)$3;
 
 			if ((pconf = malloc(sizeof(*pconf))) == NULL) {
 				yywarn("can't allocate memory");
@@ -840,7 +863,7 @@ addressparam:
 prefixparam:
 		STRING SLASH NUMBER duration
 		{
-			struct dhcp6_prefix pconf0, *pconf;		
+			struct dhcp6_prefix pconf0, *pconf;
 
 			memset(&pconf0, 0, sizeof(pconf0));
 			if (inet_pton(AF_INET6, $1, &pconf0.addr) != 1) {
@@ -854,7 +877,7 @@ prefixparam:
 			if ($4 < 0)
 				pconf0.pltime = DHCP6_DURATION_INFINITE;
 			else
-				pconf0.pltime = (u_int32_t)$4;
+				pconf0.pltime = (uint32_t)$4;
 			pconf0.vltime = pconf0.pltime;
 
 			if ((pconf = malloc(sizeof(*pconf))) == NULL) {
@@ -867,7 +890,7 @@ prefixparam:
 		}
 	|	STRING SLASH NUMBER duration duration
 		{
-			struct dhcp6_prefix pconf0, *pconf;		
+			struct dhcp6_prefix pconf0, *pconf;
 
 			memset(&pconf0, 0, sizeof(pconf0));
 			if (inet_pton(AF_INET6, $1, &pconf0.addr) != 1) {
@@ -881,11 +904,11 @@ prefixparam:
 			if ($4 < 0)
 				pconf0.pltime = DHCP6_DURATION_INFINITE;
 			else
-				pconf0.pltime = (u_int32_t)$4;
+				pconf0.pltime = (uint32_t)$4;
 			if ($5 < 0)
 				pconf0.vltime = DHCP6_DURATION_INFINITE;
 			else
-				pconf0.vltime = (u_int32_t)$5;
+				pconf0.vltime = (uint32_t)$5;
 
 			if ((pconf = malloc(sizeof(*pconf))) == NULL) {
 				yywarn("can't allocate memory");
@@ -900,7 +923,7 @@ prefixparam:
 poolparam:
 		STRING duration
 		{
-			struct dhcp6_poolspec* pool;		
+			struct dhcp6_poolspec* pool;
 
 			if ((pool = malloc(sizeof(*pool))) == NULL) {
 				yywarn("can't allocate memory");
@@ -918,14 +941,14 @@ poolparam:
 			if ($2 < 0)
 				pool->pltime = DHCP6_DURATION_INFINITE;
 			else
-				pool->pltime = (u_int32_t)$2;
+				pool->pltime = (uint32_t)$2;
 			pool->vltime = pool->pltime;
 
 			$$ = pool;
 		}
 	|	STRING duration duration
 		{
-			struct dhcp6_poolspec* pool;		
+			struct dhcp6_poolspec* pool;
 
 			if ((pool = malloc(sizeof(*pool))) == NULL) {
 				yywarn("can't allocate memory");
@@ -943,11 +966,11 @@ poolparam:
 			if ($2 < 0)
 				pool->pltime = DHCP6_DURATION_INFINITE;
 			else
-				pool->pltime = (u_int32_t)$2;
+				pool->pltime = (uint32_t)$2;
 			if ($3 < 0)
 				pool->vltime = DHCP6_DURATION_INFINITE;
 			else
-				pool->vltime = (u_int32_t)$3;
+				pool->vltime = (uint32_t)$3;
 
 			$$ = pool;
 		}
@@ -1196,12 +1219,64 @@ keyparam:
 
 %%
 /* supplement routines for configuration */
+
+struct rawoption*
+make_rawoption(int opnum, char *datastr)
+{
+			struct rawoption *rawop;
+			char *tmp;
+			yywarn("Got raw option: %i %s", opnum, datastr);
+
+			if ((rawop = malloc(sizeof(*rawop))) == NULL) {
+				yywarn("RAW can't allocate memory");
+				free(datastr);
+				return NULL;
+			}
+
+			/* convert op num */
+			rawop->opnum = opnum;
+
+			/* convert string to lowercase */
+			tmp = datastr;
+			for ( ; *tmp; ++tmp) *tmp = tolower(*tmp);
+
+			/* allocate buffer */
+			int len = strlen(datastr);
+			len -= len / 3; /* remove ':' from length */
+			len = len / 2; /* byte length */
+			rawop->datalen = len;
+
+			if ((rawop->data = malloc(len)) == NULL) {
+				yywarn("can't allocate memory");
+				free(rawop);
+				free(datastr);
+				return NULL;
+			}
+
+			/* convert hex string to byte array */
+			char *h = datastr;
+			char *b = rawop->data;
+			char xlate[] = "0123456789abcdef";
+			int p1, p2;
+
+			for ( ; *h; h += 3, ++b) { /* string is xx(:xx)\0 */
+				p1 = (int)(strchr(xlate, *h) - xlate);
+				p2 = (int)(strchr(xlate, *(h+1)) - xlate);
+				*b = (char)((p1 * 16) + p2);
+			}
+			free(datastr);
+
+			yywarn("Raw option %d length %d stored at %p with data at %p",
+				rawop->opnum, rawop->datalen, (void*)rawop, (void*)rawop->data);
+
+			return rawop;
+}
+
 static int
-add_namelist(new, headp)
-	struct cf_namelist *new, **headp;
+add_namelist(struct cf_namelist *new, struct cf_namelist **headp)
 {
 	struct cf_namelist *n;
-	
+
 	/* check for duplicated configuration */
 	for (n = *headp; n; n = n->next) {
 		if (strcmp(n->name, new->name) == 0) {
@@ -1220,7 +1295,7 @@ add_namelist(new, headp)
 
 /* free temporary resources */
 static void
-cleanup()
+cleanup(void)
 {
 	cleanup_namelist(iflist_head);
 	iflist_head = NULL;
@@ -1262,8 +1337,7 @@ cleanup()
 }
 
 static void
-cleanup_namelist(head)
-	struct cf_namelist *head;
+cleanup_namelist(struct cf_namelist *head)
 {
 	struct cf_namelist *ifp, *ifp_next;
 
@@ -1276,8 +1350,7 @@ cleanup_namelist(head)
 }
 
 static void
-cleanup_cflist(p)
-	struct cf_list *p;
+cleanup_cflist(struct cf_list *p)
 {
 	struct cf_list *n;
 
@@ -1288,8 +1361,19 @@ cleanup_cflist(p)
 	if (p->type == DECL_ADDRESSPOOL) {
 		free(((struct dhcp6_poolspec *)p->ptr)->name);
 	}
-	if (p->ptr)
+	/* Need to clean RAWOption data buffer */
+	if (p->ptr){
+		switch (p->type) {
+		case DHCPOPT_RAW:{
+			struct rawoption *rawoption = p->ptr;
+			yywarn ("Releasing raw option num %i datalen %i\n", rawoption->opnum, rawoption->datalen);
+			free (rawoption->data);
+		}
+			break;
+		default:;
+		}
 		free(p->ptr);
+	}
 	if (p->list)
 		cleanup_cflist(p->list);
 	free(p);
@@ -1301,7 +1385,7 @@ cleanup_cflist(p)
 	do { cleanup(); configure_cleanup(); return (-1); } while(0)
 
 int
-cf_post_config()
+cf_post_config(void)
 {
 	if (configure_keys(keylist_head))
 		config_fail();
@@ -1334,7 +1418,27 @@ cf_post_config()
 #undef config_fail
 
 void
-cf_init()
+cf_init(void)
 {
+#if YYDEBUG
+	yydebug = 1;
+#endif
 	iflist_head = NULL;
+	hostlist_head = NULL;
+	iapdlist_head = NULL;
+	ianalist_head = NULL;
+	authinfolist_head = NULL;
+	keylist_head = NULL;
+	addrpoollist_head = NULL;
+	cf_sip_list = NULL;
+	cf_sip_name_list = NULL;
+	cf_dns_list = NULL;
+	cf_dns_name_list = NULL;
+	cf_ntp_list = NULL;
+	cf_nis_list = NULL;
+	cf_nis_name_list = NULL;
+	cf_nisp_list = NULL;
+	cf_nisp_name_list = NULL;
+	cf_bcmcs_list = NULL;
+	cf_bcmcs_name_list = NULL;
 }
