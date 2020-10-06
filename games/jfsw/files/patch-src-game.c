Rename PREFIX to DATADIR as its only purpose is to access data files
(and avoid conflict with FreeBSD PREFIX, which has another meaning)

--- src/game.c.orig	2020-09-22 22:08:35 UTC
+++ src/game.c
@@ -3405,9 +3405,9 @@ int app_main(int argc, char const * const argv[])
     }
 #endif
 
-#if defined(PREFIX)
+#if defined(DATADIR)
     {
-        const char *prefixdir = PREFIX;
+        const char *prefixdir = DATADIR;
         if (prefixdir && prefixdir[0]) {
             addsearchpath(prefixdir);
         }
