--- common.h.orig	2007-03-21 09:52:57 UTC
+++ common.h
@@ -179,6 +179,7 @@ extern void duidfree __P((struct duid *)
 extern int ifaddrconf __P((ifaddrconf_cmd_t, char *, struct sockaddr_in6 *,
 			   int, int, int));
 extern int safefile __P((const char *));
+extern int opt_norelease;
 
 /* missing */
 #ifndef HAVE_STRLCAT
