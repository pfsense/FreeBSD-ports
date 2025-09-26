--- lease.h.orig	2017-02-28 19:06:15 UTC
+++ lease.h
@@ -27,14 +27,14 @@
  * SUCH DAMAGE.
  */
 
-#ifndef __LEASE_H_DEFINED
-#define __LEASE_H_DEFINED
+#ifndef _LEASE_H_
+#define _LEASE_H_
 
-extern int lease_init __P((void));
-extern void lease_cleanup __P((void));
-extern int lease_address __P((struct in6_addr *));
-extern void release_address __P((struct in6_addr *));
-extern void decline_address __P((struct in6_addr *));
-extern int is_leased __P((struct in6_addr *));
+int lease_init(void);
+void lease_cleanup(void);
+int lease_address(struct in6_addr *);
+void release_address(struct in6_addr *);
+void decline_address(struct in6_addr *);
+int is_leased(struct in6_addr *);
 
 #endif
