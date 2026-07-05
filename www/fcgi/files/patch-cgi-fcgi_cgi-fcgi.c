--- cgi-fcgi/cgi-fcgi.c.orig	2025-05-23 01:57:11 UTC
+++ cgi-fcgi/cgi-fcgi.c
@@ -812,11 +812,9 @@ int main(int argc, char **argv)
                 for(pid=nServers; pid != 0; pid--) {
                     wait(0);
                 }
+                signal(SIGTERM, SIG_IGN);
+                kill(0, SIGTERM);
             }
-#endif
-            signal(SIGTERM, SIG_IGN);
-#ifndef _WIN32
-            kill(0, SIGTERM);
 #endif
             exit(0);
         } else {
