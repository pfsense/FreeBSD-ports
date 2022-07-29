--- addrconf.h.orig	2017-02-28 19:06:15 UTC
+++ addrconf.h
@@ -29,7 +29,12 @@
  * SUCH DAMAGE.
  */
 
+#ifndef	_ADDRCONF_H_
+#define	_ADDRCONF_H_
+
 typedef enum { ADDR6S_ACTIVE, ADDR6S_RENEW, ADDR6S_REBIND} addr6state_t;
 
-extern int update_address __P((struct ia *, struct dhcp6_statefuladdr *,
-    struct dhcp6_if *, struct iactl **, void (*)__P((struct ia *))));
+int update_address(struct ia *, struct dhcp6_statefuladdr *,
+    struct dhcp6_if *, struct iactl **, void (*)(struct ia *));
+
+#endif
