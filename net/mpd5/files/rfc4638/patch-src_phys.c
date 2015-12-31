--- src/phys.c.orig	2013-06-11 09:00:00 UTC
+++ src/phys.c
@@ -466,6 +466,52 @@ PhysGetCalledNum(Link l, char *buf, size
 }
 
 /*
+ * PhysGetMtu()
+ */
+
+u_short
+PhysGetMtu(Link l, int conf)
+{
+    PhysType	const pt = l->type;
+
+    if (pt) {
+	if (pt->getmtu)
+	    return ((*pt->getmtu)(l, conf));
+	if (conf == 0) {
+	    if (pt->mtu)
+		return (pt->mtu);
+	    else
+		return (0);
+	} else
+	    return (l->conf.mtu);
+    } else
+	return (0);
+}
+
+/*
+ * PhysGetMru()
+ */
+
+u_short
+PhysGetMru(Link l, int conf)
+{
+    PhysType	const pt = l->type;
+
+    if (pt) {
+	if (pt->getmru)
+	    return ((*pt->getmru)(l, conf));
+	if (conf == 0) {
+	    if (pt->mru)
+		return (pt->mru);
+	    else
+		return (0);
+	} else
+	    return (l->conf.mru);
+    } else
+	return (0);
+}
+
+/*
  * PhysIsBusy()
  *
  * This returns 1 if link is busy
