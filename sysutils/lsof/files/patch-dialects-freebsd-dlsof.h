--- dialects/freebsd/dlsof.h.orig	2019-05-08 07:32:25 UTC
+++ dialects/freebsd/dlsof.h
@@ -38,6 +38,7 @@
 #if	!defined(FREEBSD_LSOF_H)
 #define	FREEBSD_LSOF_H	1
 
+#include <stddef.h>
 #include <stdlib.h>
 #include <dirent.h>
 #include <nlist.h>
@@ -45,6 +46,12 @@
 #include <signal.h>
 #include <unistd.h>
 
+#if    FREEBSDV>=13000
+/* This header is a huge mess.  Please don't support EOL FreeBSD releases. */
+#define        _KERNEL 1
+#include <sys/_lock.h>
+#undef _KERNEL
+#endif         /* FREEBSDV>=13000 */
 # if	FREEBSDV>=4000
 #  if	FREEBSDV>=5000
 #   if	FREEBSDV<6020
@@ -105,6 +112,12 @@ typedef	struct device	*device_t;
 
 #include <sys/conf.h>
 
+/* 
+ * include <stdbool.h> for refcount(9)
+ */
+#include <stdbool.h>
+ 
+
 #  if	defined(HAS_VM_MEMATTR_T)
 #undef	vm_memattr_t
 #  endif	/* defined(HAS_VM_MEMATTR_T) */
@@ -652,9 +665,15 @@ struct sfile {
  */
 
 struct	namecache {
+#   if  __FreeBSD_version < 1202000 || (__FreeBSD_version >= 1300000 && __FreeBSD_version < 1300105)
 	LIST_ENTRY(namecache) nc_hash;	/* hash chain */
 	LIST_ENTRY(namecache) nc_src;	/* source vnode list */
 	TAILQ_ENTRY(namecache) nc_dst;	/* destination vnode list */
+#   else
+	LIST_ENTRY(namecache) nc_src;	/* source vnode list */
+	TAILQ_ENTRY(namecache) nc_dst;	/* destination vnode list */
+	LIST_ENTRY(namecache) nc_hash;	/* hash chain */
+#   endif
 	struct	vnode *nc_dvp;		/* vnode of parent of name */
 	struct	vnode *nc_vp;		/* vnode the name refers to */
 	u_char	nc_flag;		/* flag bits */
