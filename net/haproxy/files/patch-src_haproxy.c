--- src/haproxy.c.orig	2017-04-03 08:28:32 UTC
+++ src/haproxy.c
@@ -1312,7 +1312,7 @@ void init(int argc, char **argv)
 		exit(1);
 
 	/* initialize structures for name resolution */
-	if (!dns_init_resolvers(0))
+	if (!dns_init_resolvers())
 		exit(1);
 
 	free(err_msg);
@@ -2094,10 +2094,6 @@ int main(int argc, char **argv)
 		fork_poller();
 	}
 
-	/* initialize structures for name resolution */
-	if (!dns_init_resolvers(1))
-		exit(1);
-
 	protocol_enable_all();
 	/*
 	 * That's it : the central polling loop. Run until we stop.
