--- common.h.orig	2017-02-28 19:06:15 UTC
+++ common.h
@@ -28,6 +28,9 @@
  * SUCH DAMAGE.
  */
 
+#ifndef	_COMMON_H_
+#define	_COMMON_H_
+
 #ifdef __KAME__
 #define IN6_IFF_INVALID (IN6_IFF_ANYCAST|IN6_IFF_TENTATIVE|\
 		IN6_IFF_DUPLICATED|IN6_IFF_DETACHED)
@@ -100,7 +103,7 @@
 #endif
 
 /* s*_len stuff */
-static __inline u_int8_t
+static __inline uint8_t
 sysdep_sa_len (const struct sockaddr *sa)
 {
 #ifndef HAVE_SA_LEN
@@ -127,64 +130,71 @@ extern int opt_norelease;
 
 /* common.c */
 typedef enum { IFADDRCONF_ADD, IFADDRCONF_REMOVE } ifaddrconf_cmd_t;
-extern int dhcp6_copy_list __P((struct dhcp6_list *, struct dhcp6_list *));
-extern void dhcp6_move_list __P((struct dhcp6_list *, struct dhcp6_list *));
-extern void dhcp6_clear_list __P((struct dhcp6_list *));
-extern void dhcp6_clear_listval __P((struct dhcp6_listval *));
-extern struct dhcp6_listval *dhcp6_find_listval __P((struct dhcp6_list *,
-    dhcp6_listval_type_t, void *, int));
-extern struct dhcp6_listval *dhcp6_add_listval __P((struct dhcp6_list *,
-    dhcp6_listval_type_t, void *, struct dhcp6_list *));
-extern int dhcp6_vbuf_copy __P((struct dhcp6_vbuf *, struct dhcp6_vbuf *));
-extern void dhcp6_vbuf_free __P((struct dhcp6_vbuf *));
-extern int dhcp6_vbuf_cmp __P((struct dhcp6_vbuf *, struct dhcp6_vbuf *));
-extern struct dhcp6_event *dhcp6_create_event __P((struct dhcp6_if *, int));
-extern void dhcp6_remove_event __P((struct dhcp6_event *));
-extern void dhcp6_remove_evdata __P((struct dhcp6_event *));
-extern struct authparam *new_authparam __P((int, int, int));
-extern struct authparam *copy_authparam __P((struct authparam *));
-extern int dhcp6_auth_replaycheck __P((int, u_int64_t, u_int64_t));
-extern int getifaddr __P((struct in6_addr *, char *, struct in6_addr *,
-			  int, int, int));
-extern int getifidfromaddr __P((struct in6_addr *, unsigned int *));
-extern int transmit_sa __P((int, struct sockaddr *, char *, size_t));
-extern long random_between __P((long, long));
-extern int prefix6_mask __P((struct in6_addr *, int));
-extern int sa6_plen2mask __P((struct sockaddr_in6 *, int));
-extern char *addr2str __P((struct sockaddr *));
-extern char *in6addr2str __P((struct in6_addr *, int));
-extern int in6_addrscopebyif __P((struct in6_addr *, char *));
-extern int in6_scope __P((struct in6_addr *));
-extern void setloglevel __P((int));
-extern void d_printf __P((int, const char *, const char *, ...));
-extern int get_duid __P((char *, struct duid *));
-extern void dhcp6_init_options __P((struct dhcp6_optinfo *));
-extern void dhcp6_clear_options __P((struct dhcp6_optinfo *));
-extern int dhcp6_copy_options __P((struct dhcp6_optinfo *,
-				   struct dhcp6_optinfo *));
-extern int dhcp6_get_options __P((struct dhcp6opt *, struct dhcp6opt *,
-				  struct dhcp6_optinfo *));
-extern int dhcp6_set_options __P((int, struct dhcp6opt *, struct dhcp6opt *,
-				  struct dhcp6_optinfo *));
-extern void dhcp6_set_timeoparam __P((struct dhcp6_event *));
-extern void dhcp6_reset_timer __P((struct dhcp6_event *));
-extern char *dhcp6optstr __P((int));
-extern char *dhcp6msgstr __P((int));
-extern char *dhcp6_stcodestr __P((u_int16_t));
-extern char *duidstr __P((struct duid *));
-extern char *dhcp6_event_statestr __P((struct dhcp6_event *));
-extern int get_rdvalue __P((int, void *, size_t));
-extern int duidcpy __P((struct duid *, struct duid *));
-extern int duidcmp __P((struct duid *, struct duid *));
-extern void duidfree __P((struct duid *));
-extern int ifaddrconf __P((ifaddrconf_cmd_t, char *, struct sockaddr_in6 *,
-			   int, int, int));
-extern int safefile __P((const char *));
+int rawop_copy_list(struct rawop_list *, struct rawop_list *);
+void rawop_clear_list(struct rawop_list *);
+int dhcp6_copy_list(struct dhcp6_list *, struct dhcp6_list *);
+void dhcp6_move_list(struct dhcp6_list *, struct dhcp6_list *);
+void dhcp6_clear_list(struct dhcp6_list *);
+void dhcp6_clear_listval(struct dhcp6_listval *);
+struct dhcp6_listval *dhcp6_find_listval(struct dhcp6_list *,
+    dhcp6_listval_type_t, void *, int);
+struct dhcp6_listval *dhcp6_add_listval(struct dhcp6_list *,
+    dhcp6_listval_type_t, void *, struct dhcp6_list *);
+int dhcp6_vbuf_copy(struct dhcp6_vbuf *, struct dhcp6_vbuf *);
+void dhcp6_vbuf_free(struct dhcp6_vbuf *);
+int dhcp6_vbuf_cmp(struct dhcp6_vbuf *, struct dhcp6_vbuf *);
+struct dhcp6_event *dhcp6_create_event(struct dhcp6_if *, int);
+void dhcp6_remove_event(struct dhcp6_event *);
+void dhcp6_remove_evdata(struct dhcp6_event *);
+struct authparam *new_authparam(int, int, int);
+struct authparam *copy_authparam(struct authparam *);
+int dhcp6_auth_replaycheck(int, uint64_t, uint64_t);
+int getifaddr(struct in6_addr *, char *, struct in6_addr *,
+			  int, int, int);
+int getifidfromaddr(struct in6_addr *, unsigned int *);
+int transmit_sa(int, struct sockaddr *, char *, size_t);
+long random_between(long, long);
+int prefix6_mask(struct in6_addr *, int);
+int sa6_plen2mask(struct sockaddr_in6 *, int);
+char *addr2str(struct sockaddr *);
+char *in6addr2str(struct in6_addr *, int);
+int in6_addrscopebyif(struct in6_addr *, char *);
+int in6_scope(struct in6_addr *);
+void setloglevel(int);
+void d_printf(int, const char *, const char *, ...);
+int get_duid(const char *, struct duid *);
+void dhcp6_init_options(struct dhcp6_optinfo *);
+void dhcp6_clear_options(struct dhcp6_optinfo *);
+int dhcp6_copy_options(struct dhcp6_optinfo *,
+				   struct dhcp6_optinfo *);
+int dhcp6_get_options(struct dhcp6opt *, struct dhcp6opt *,
+				  struct dhcp6_optinfo *);
+int dhcp6_set_options(int, struct dhcp6opt *, struct dhcp6opt *,
+				  struct dhcp6_optinfo *);
+void dhcp6_set_timeoparam(struct dhcp6_event *);
+void dhcp6_reset_timer(struct dhcp6_event *);
+const char *dhcp6optstr(int);
+const char *dhcp6msgstr(int);
+const char *dhcp6_stcodestr(uint16_t);
+char *duidstr(struct duid *);
+const char *dhcp6_event_statestr(struct dhcp6_event *);
+int get_rdvalue(int, void *, size_t);
+int duidcpy(struct duid *, struct duid *);
+int duidcmp(struct duid *, struct duid *);
+void duidfree(struct duid *);
+int ifaddrconf(ifaddrconf_cmd_t, char *, struct sockaddr_in6 *,
+			   int, int, int);
+int safefile(const char *);
 
 /* missing */
 #ifndef HAVE_STRLCAT
-extern size_t strlcat __P((char *, const char *, size_t));
+size_t strlcat(char *, const char *, size_t);
 #endif
 #ifndef HAVE_STRLCPY
-extern size_t strlcpy __P((char *, const char *, size_t));
+size_t strlcpy(char *, const char *, size_t);
+#endif
+
+int get_val32(char **bpp, int *lenp, uint32_t *valp);
+int get_val(char **bpp, int *lenp, void *valp, size_t vallen);
+
 #endif
