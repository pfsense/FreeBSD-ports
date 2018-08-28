--- cgi-fcgi/cgi-fcgi.c.orig	2018-08-28 13:21:00 UTC
+++ cgi-fcgi/cgi-fcgi.c
@@ -531,7 +531,7 @@ static void FCGI_Start(char *bindPath, c
             exit(OS_Errno);
 	}
     }
-    OS_Close(listenFd);
+    //OS_Close(listenFd);
 }
 
 /*
