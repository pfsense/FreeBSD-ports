--- config.h.orig	2017-02-28 19:06:15 UTC
+++ config.h
@@ -29,14 +29,17 @@
  * SUCH DAMAGE.
  */
 
+#ifndef	_CONFIG_H_
+#define	_CONFIG_H_
+
 /* definitions of tail-queue types */
 TAILQ_HEAD(ia_conflist, ia_conf);
 TAILQ_HEAD(pifc_list, prefix_ifconf);
 
 struct dhcp6_poolspec {
 	char* name;
-	u_int32_t pltime;
-	u_int32_t vltime;
+	uint32_t pltime;
+	uint32_t vltime;
 };
 
 struct dhcp6_range {
@@ -66,7 +69,7 @@ struct dhcp6_if {
 	/* static parameters of the interface */
 	char *ifname;
 	unsigned int ifid;
-	u_int32_t linkid;	/* to send link-local packets */
+	uint32_t linkid;	/* to send link-local packets */
 	/* multiple global address configuration is not supported now */
 	struct in6_addr addr; 	/* global address */
 
@@ -80,6 +83,10 @@ struct dhcp6_if {
 	struct dhcp6_poolspec pool;	/* address pool (server only) */
 	char *scriptpath;	/* path to config script (client only) */
 
+	/* XXX */
+	struct duid duid;
+	struct rawop_list rawops;
+
 	struct dhcp6_list reqopt_list;
 	struct ia_conflist iaconf_list;
 
@@ -99,7 +106,7 @@ struct authparam {
 	int flags;
 #define AUTHPARAM_FLAGS_NOPREVRD	0x1
 
-	u_int64_t prevrd;	/* previous RD value provided by the peer */
+	uint64_t prevrd;	/* previous RD value provided by the peer */
 };
 
 struct dhcp6_event {
@@ -120,7 +127,7 @@ struct dhcp6_event {
 	long max_retrans_dur;
 	int timeouts;		/* number of timeouts */
 
-	u_int32_t xid;		/* current transaction ID */
+	uint32_t xid;		/* current transaction ID */
 	int state;
 
 	/* list of known servers */
@@ -142,7 +149,7 @@ struct dhcp6_eventdata {
 	dhcp6_eventdata_t type;
 	void *data;
 
-	void (*destructor) __P((struct dhcp6_eventdata *));
+	void (*destructor)(struct dhcp6_eventdata *);
 	void *privdata;
 };
 
@@ -170,10 +177,12 @@ struct prefix_ifconf {
 
 	char *ifname;		/* interface name such as ne0 */
 	int sla_len;		/* SLA ID length in bits */
-	u_int32_t sla_id;	/* need more than 32bits? */
+	uint32_t sla_id;	/* need more than 32bits? */
 	int ifid_len;		/* interface ID length in bits */
 	int ifid_type;		/* EUI-64 and manual (unused?) */
 	char ifid[16];		/* Interface ID, up to 128bits */
+
+	struct sockaddr_in6 *ifaddr;
 };
 #define IFID_LEN_DEFAULT 64
 #define SLA_LEN_DEFAULT 16
@@ -183,7 +192,7 @@ struct ia_conf {
 	TAILQ_ENTRY(ia_conf) link;
 	/*struct ia_conf *next;*/
 	iatype_t type;
-	u_int32_t iaid;
+	uint32_t iaid;
 
 	TAILQ_HEAD(, ia) iadata; /* struct ia is an opaque type */
 
@@ -227,7 +236,7 @@ struct host_conf {
 	struct keyinfo *delayedkey;
 	/* previous replay detection value from the client */
 	int saw_previous_rd;	/* if we remember the previous value */
-	u_int64_t previous_rd;
+	uint64_t previous_rd;
 };
 
 /* DHCPv6 authentication information */
@@ -279,7 +288,9 @@ enum { DECL_SEND, DECL_ALLOW, DECL_INFO_ONLY, DECL_REQ
        IACONF_PIF, IACONF_PREFIX, IACONF_ADDR,
        DHCPOPT_SIP, DHCPOPT_SIPNAME,
        AUTHPARAM_PROTO, AUTHPARAM_ALG, AUTHPARAM_RDM, AUTHPARAM_KEY,
-       KEYPARAM_REALM, KEYPARAM_KEYID, KEYPARAM_SECRET, KEYPARAM_EXPIRE };
+       KEYPARAM_REALM, KEYPARAM_KEYID, KEYPARAM_SECRET, KEYPARAM_EXPIRE,
+       /* XXX */
+       DHCPOPT_RAW };
 
 typedef enum {DHCP6_MODE_SERVER, DHCP6_MODE_CLIENT, DHCP6_MODE_RELAY }
 dhcp6_mode_t;
@@ -301,32 +312,35 @@ extern struct dhcp6_list nispnamelist;
 extern struct dhcp6_list bcmcslist;
 extern struct dhcp6_list bcmcsnamelist;
 extern long long optrefreshtime;
+extern int use_all_config_if;
 
-extern struct dhcp6_if *ifinit __P((char *));
-extern int ifreset __P((struct dhcp6_if *));
-extern int configure_interface __P((struct cf_namelist *));
-extern int configure_host __P((struct cf_namelist *));
-extern int configure_keys __P((struct cf_namelist *));
-extern int configure_authinfo __P((struct cf_namelist *));
-extern int configure_ia __P((struct cf_namelist *, iatype_t));
-extern int configure_global_option __P((void));
-extern void configure_cleanup __P((void));
-extern void configure_commit __P((void));
-extern int cfparse __P((char *));
-extern struct dhcp6_if *find_ifconfbyname __P((char *));
-extern struct dhcp6_if *find_ifconfbyid __P((unsigned int));
-extern struct prefix_ifconf *find_prefixifconf __P((char *));
-extern struct host_conf *find_hostconf __P((struct duid *));
-extern struct authinfo *find_authinfo __P((struct authinfo *, char *));
-extern struct dhcp6_prefix *find_prefix6 __P((struct dhcp6_list *,
-					      struct dhcp6_prefix *));
-extern struct ia_conf *find_iaconf __P((struct ia_conflist *, int, u_int32_t));
-extern struct keyinfo *find_key __P((char *, size_t, u_int32_t));
-extern int configure_pool __P((struct cf_namelist *));
-extern struct pool_conf *find_pool __P((const char *));
-extern int is_available_in_pool __P((struct pool_conf *, struct in6_addr *));
-extern int get_free_address_from_pool __P((struct pool_conf *,
-	struct in6_addr *));
-struct host_conf *create_dynamic_hostconf __P((struct duid *,
-	struct dhcp6_poolspec *));
-extern char *qstrdup __P((char *));
+struct dhcp6_if *ifinit(char *);
+int ifreset(struct dhcp6_if *);
+int configure_interface(struct cf_namelist *);
+int configure_host(struct cf_namelist *);
+int configure_keys(struct cf_namelist *);
+int configure_authinfo(struct cf_namelist *);
+int configure_ia(struct cf_namelist *, iatype_t);
+int configure_global_option(void);
+void configure_cleanup(void);
+void configure_commit(void);
+int cfparse(const char *);
+struct dhcp6_if *find_ifconfbyname(char *);
+struct dhcp6_if *find_ifconfbyid(unsigned int);
+struct prefix_ifconf *find_prefixifconf(char *);
+struct host_conf *find_hostconf(struct duid *);
+struct authinfo *find_authinfo(struct authinfo *, char *);
+struct dhcp6_prefix *find_prefix6(struct dhcp6_list *,
+					      struct dhcp6_prefix *);
+struct ia_conf *find_iaconf(struct ia_conflist *, int, uint32_t);
+struct keyinfo *find_key(char *, size_t, uint32_t);
+int configure_pool(struct cf_namelist *);
+struct pool_conf *find_pool(const char *);
+int is_available_in_pool(struct pool_conf *, struct in6_addr *);
+int get_free_address_from_pool(struct pool_conf *,
+	struct in6_addr *);
+struct host_conf *create_dynamic_hostconf(struct duid *,
+	struct dhcp6_poolspec *);
+char *qstrdup(char *);
+
+#endif
