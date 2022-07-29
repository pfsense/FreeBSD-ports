--- addrconf.c.orig	2017-02-28 19:06:15 UTC
+++ addrconf.c
@@ -57,8 +57,10 @@
 #include "config.h"
 #include "common.h"
 #include "timer.h"
+#include "dhcp6c.h"
 #include "dhcp6c_ia.h"
 #include "prefixconf.h"
+#include "addrconf.h"
 
 TAILQ_HEAD(statefuladdr_list, statefuladdr);
 struct iactl_na {
@@ -85,29 +87,24 @@ struct statefuladdr {
 	struct dhcp6_if *dhcpif;
 };
 
-static struct statefuladdr *find_addr __P((struct statefuladdr_list *,
-    struct dhcp6_statefuladdr *));
-static int remove_addr __P((struct statefuladdr *));
-static int isvalid_addr __P((struct iactl *));
-static u_int32_t duration_addr __P((struct iactl *));
-static void cleanup_addr __P((struct iactl *));
-static int renew_addr __P((struct iactl *, struct dhcp6_ia *,
-    struct dhcp6_eventdata **, struct dhcp6_eventdata *));
-static void na_renew_data_free __P((struct dhcp6_eventdata *));
+static struct statefuladdr *find_addr(struct statefuladdr_list *,
+    struct dhcp6_statefuladdr *);
+static int remove_addr(struct statefuladdr *);
+static int isvalid_addr(struct iactl *);
+static uint32_t duration_addr(struct iactl *);
+static void cleanup_addr(struct iactl *);
+static int renew_addr(struct iactl *, struct dhcp6_ia *,
+    struct dhcp6_eventdata **, struct dhcp6_eventdata *);
+static void na_renew_data_free(struct dhcp6_eventdata *);
 
-static struct dhcp6_timer *addr_timo __P((void *));
+static struct dhcp6_timer *addr_timo(void *);
 
-static int na_ifaddrconf __P((ifaddrconf_cmd_t, struct statefuladdr *));
+static int na_ifaddrconf(ifaddrconf_cmd_t, struct statefuladdr *);
 
-extern struct dhcp6_timer *client6_timo __P((void *));
-
 int
-update_address(ia, addr, dhcpifp, ctlp, callback)
-	struct ia *ia;
-	struct dhcp6_statefuladdr *addr;
-	struct dhcp6_if *dhcpifp;
-	struct iactl **ctlp;
-	void (*callback)__P((struct ia *));
+update_address(struct ia *ia, struct dhcp6_statefuladdr *addr,
+    struct dhcp6_if *dhcpifp, struct iactl **ctlp,
+    void (*callback)(struct ia *))
 {
 	struct iactl_na *iac_na = (struct iactl_na *)*ctlp;
 	struct statefuladdr *sa;
@@ -212,9 +209,7 @@ update_address(ia, addr, dhcpifp, ctlp, callback)
 }
 
 static struct statefuladdr *
-find_addr(head, addr)
-	struct statefuladdr_list *head;
-	struct dhcp6_statefuladdr *addr;
+find_addr(struct statefuladdr_list *head, struct dhcp6_statefuladdr *addr)
 {
 	struct statefuladdr *sa;
 
@@ -228,8 +223,7 @@ find_addr(head, addr)
 }
 
 static int
-remove_addr(sa)
-	struct statefuladdr *sa;
+remove_addr(struct statefuladdr *sa)
 {
 	int ret;
 
@@ -247,8 +241,7 @@ remove_addr(sa)
 }
 
 static int
-isvalid_addr(iac)
-	struct iactl *iac;
+isvalid_addr(struct iactl *iac)
 {
 	struct iactl_na *iac_na = (struct iactl_na *)iac;
 
@@ -257,13 +250,12 @@ isvalid_addr(iac)
 	return (1);
 }
 
-static u_int32_t
-duration_addr(iac)
-	struct iactl *iac;
+static uint32_t
+duration_addr(struct iactl *iac)
 {
 	struct iactl_na *iac_na = (struct iactl_na *)iac;
 	struct statefuladdr *sa;
-	u_int32_t base = DHCP6_DURATION_INFINITE, pltime, passed;
+	uint32_t base = DHCP6_DURATION_INFINITE, pltime, passed;
 	time_t now;
 
 	/* Determine the smallest period until pltime expires. */
@@ -271,7 +263,7 @@ duration_addr(iac)
 	for (sa = TAILQ_FIRST(&iac_na->statefuladdr_head); sa;
 	    sa = TAILQ_NEXT(sa, link)) {
 		passed = now > sa->updatetime ?
-		    (u_int32_t)(now - sa->updatetime) : 0;
+		    (uint32_t)(now - sa->updatetime) : 0;
 		pltime = sa->addr.pltime > passed ?
 		    sa->addr.pltime - passed : 0;
 
@@ -283,8 +275,7 @@ duration_addr(iac)
 }
 
 static void
-cleanup_addr(iac)
-	struct iactl *iac;
+cleanup_addr(struct iactl *iac)
 {
 	struct iactl_na *iac_na = (struct iactl_na *)iac;
 	struct statefuladdr *sa;
@@ -298,10 +289,8 @@ cleanup_addr(iac)
 }
 
 static int
-renew_addr(iac, iaparam, evdp, evd)
-	struct iactl *iac;
-	struct dhcp6_ia *iaparam;
-	struct dhcp6_eventdata **evdp, *evd;
+renew_addr(struct iactl *iac, struct dhcp6_ia *iaparam,
+    struct dhcp6_eventdata **evdp, struct dhcp6_eventdata *evd)
 {
 	struct iactl_na *iac_na = (struct iactl_na *)iac;
 	struct statefuladdr *sa;
@@ -337,8 +326,7 @@ renew_addr(iac, iaparam, evdp, evd)
 }
 
 static void
-na_renew_data_free(evd)
-	struct dhcp6_eventdata *evd;
+na_renew_data_free(struct dhcp6_eventdata *evd)
 {
 	struct dhcp6_list *ial;
 
@@ -355,12 +343,11 @@ na_renew_data_free(evd)
 }
 
 static struct dhcp6_timer *
-addr_timo(arg)
-	void *arg;
+addr_timo(void *arg)
 {
 	struct statefuladdr *sa = (struct statefuladdr *)arg;
 	struct ia *ia;
-	void (*callback)__P((struct ia *));
+	void (*callback)(struct ia *);
 
 	d_printf(LOG_DEBUG, FNAME, "address timeout for %s",
 	    in6addr2str(&sa->addr.addr, 0));
@@ -379,9 +366,7 @@ addr_timo(arg)
 }
 
 static int
-na_ifaddrconf(cmd, sa)
-	ifaddrconf_cmd_t cmd;
-	struct statefuladdr *sa;
+na_ifaddrconf(ifaddrconf_cmd_t cmd, struct statefuladdr *sa)
 {
 	struct dhcp6_statefuladdr *addr;
 	struct sockaddr_in6 sin6;
