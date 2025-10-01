--- vmblock/subr.c.orig	2025-10-01 18:57:53 UTC
+++ vmblock/subr.c
@@ -208,7 +208,7 @@ VMBlockHashGet(struct mount *mp,        // IN: vmblock
       }
    }
    mtx_unlock(&hashMutex);
-   return NULLVP;
+   return NULL;
 }
 
 
@@ -222,7 +222,7 @@ VMBlockHashGet(struct mount *mp,        // IN: vmblock
  *
  * Results:
  *      Referenced, locked alias vnode if entry already in hash.  Otherwise
- *      NULLVP.
+ *      NULL.
  *
  * Side effects:
  *      None.
@@ -254,7 +254,7 @@ VMBlockHashInsert(struct mount *mp,             // IN:
    }
    LIST_INSERT_HEAD(hd, xp, hashEntry);
    mtx_unlock(&hashMutex);
-   return NULLVP;
+   return NULL;
 }
 
 
@@ -465,7 +465,7 @@ VMBlockCheckVp(vp, fil, lno)
       panic("VMBlockCheckVp");
    };
 #endif
-   if (a->lowerVnode == NULLVP) {
+   if (a->lowerVnode == NULL) {
       /* Should never happen */
       int i; u_long *p;
       printf("vp = %p, ZERO ptr\n", (void *)vp);
