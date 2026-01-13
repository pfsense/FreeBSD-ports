--- dhcp6c_ia.c.orig	2017-02-28 19:06:15 UTC
+++ dhcp6c_ia.c
@@ -58,8 +58,8 @@ struct ia {
 	struct ia_conf *conf;
 
 	/* common parameters of IA */
-	u_int32_t t1;		/* duration for renewal */
-	u_int32_t t2;		/* duration for rebind  */
+	uint32_t t1;		/* duration for renewal */
+	uint32_t t2;		/* duration for rebind  */
 
 	/* internal parameters for renewal/rebinding */
 	iastate_t state;
@@ -77,18 +77,18 @@ struct ia {
 	struct authparam *authparam;
 };
 
-static int update_authparam __P((struct ia *, struct authparam *));
-static void reestablish_ia __P((struct ia *));
-static void callback __P((struct ia *));
-static int release_ia __P((struct ia *));
-static void remove_ia __P((struct ia *));
-static struct ia *get_ia __P((iatype_t, struct dhcp6_if *, struct ia_conf *,
-    struct dhcp6_listval *, struct duid *));
-static struct ia *find_ia __P((struct ia_conf *, iatype_t, u_int32_t));
-static struct dhcp6_timer *ia_timo __P((void *));
+static int update_authparam(struct ia *, struct authparam *);
+static void reestablish_ia(struct ia *);
+static void callback(struct ia *);
+static int release_ia(struct ia *);
+static void remove_ia(struct ia *);
+static struct ia *get_ia(iatype_t, struct dhcp6_if *, struct ia_conf *,
+    struct dhcp6_listval *, struct duid *);
+static struct ia *find_ia(struct ia_conf *, iatype_t, uint32_t);
+static struct dhcp6_timer *ia_timo(void *);
 
-static char *iastr __P((iatype_t));
-static char *statestr __P((iastate_t));
+static const char *iastr(iatype_t);
+static const char *statestr(iastate_t);
 
 void
 update_ia(iatype, ialist, ifp, serverid, authparam)
@@ -212,7 +212,7 @@ update_ia(iatype, ialist, ifp, serverid, authparam)
 
 		/* if T1 or T2 is 0, determine appropriate values locally. */
 		if (ia->t1 == 0 || ia->t2 == 0) {
-			u_int32_t duration;
+			uint32_t duration;
 
 			if (ia->ctl && ia->ctl->duration)
 				duration = (*ia->ctl->duration)(ia->ctl);
@@ -734,7 +734,7 @@ static struct ia *
 find_ia(iac, type, iaid)
 	struct ia_conf *iac;
 	iatype_t type;
-	u_int32_t iaid;
+	uint32_t iaid;
 {
 	struct ia *ia;
 
@@ -747,7 +747,7 @@ find_ia(iac, type, iaid)
 	return (NULL);
 }
 
-static char *
+const static char *
 iastr(type)
 	iatype_t type;
 {
@@ -761,7 +761,7 @@ iastr(type)
 	}
 }
 
-static char *
+static const char *
 statestr(state)
 	iastate_t state;
 {
