--- main.c.orig	2024-05-10 07:10:35 UTC
+++ main.c
@@ -323,6 +323,12 @@ srandom(time(NULL) & getpid());
       };
 
 
+    int yes = 1;
+    if (setsockopt(fd, SOL_SOCKET, SO_REUSEPORT, &yes, sizeof(yes)) != 0) {
+        perror("setsockopt");
+        exit(1);
+    }
+
     /* For each interface on the command line */
     int maxifs = 0;
     for (int i = 0; i < interfaceNamesNum; i++) {
@@ -801,4 +807,4 @@ srandom(time(NULL) & getpid());
         }
         DPRINT ("\n");
     }
-}
\ No newline at end of file
+}
