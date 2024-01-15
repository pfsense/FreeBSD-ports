--- usr/sbin/pkcsslotd/shmem.c.orig	2022-04-25 11:04:51 UTC
+++ usr/sbin/pkcsslotd/shmem.c
@@ -58,9 +58,9 @@ int CreateSharedMemory(void)
     }
     // SAB  Get the group information for the PKCS#11 group... fail if
     // it does not exist
-    grp = getgrnam("pkcs11");
+    grp = getgrnam(PKCS11GROUP);
     if (!grp) {
-        ErrLog("Group PKCS#11 does not exist ");
+        ErrLog("Group " PKCS11GROUP " does not exist ");
         return FALSE;           // Group does not exist... setup is wrong..
     }
 
@@ -141,9 +141,9 @@ int CreateSharedMemory(void)
         int i;
         char *buffer;
 
-        grp = getgrnam("pkcs11");
+        grp = getgrnam(PKCS11GROUP);
         if (!grp) {
-            ErrLog("Group \"pkcs11\" does not exist! "
+            ErrLog("Group " PKCS11GROUP " does not exist! "
                    "Opencryptoki setup is incorrect.");
             return FALSE;       // Group does not exist... setup is wrong..
         }
@@ -165,8 +165,8 @@ int CreateSharedMemory(void)
                     return FALSE;
                 }
                 if (fchown(fd, 0, grp->gr_gid) == -1) {
-                    ErrLog("%s: fchown(%s, root, pkcs11): %s", __func__,
-                           MAPFILENAME, strerror(errno));
+                    ErrLog("%s: fchown(%s, root, %s): %s", __func__,
+                           MAPFILENAME, PKCS11GROUP, strerror(errno));
                     close(fd);
                     return FALSE;
                 }
