--- src/phys.c	2013-06-11 10:00:00.000000000 +0100
+++ src/phys.c	2015-09-22 20:49:38.000000000 +0100
@@ -466,6 +466,52 @@
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
