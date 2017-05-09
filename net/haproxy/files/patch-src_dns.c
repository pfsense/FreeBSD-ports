--- src/dns.c.orig	2017-04-03 08:28:32 UTC
+++ src/dns.c
@@ -919,13 +919,11 @@ unsigned short dns_response_get_query_id
  * parses resolvers sections and initializes:
  *  - task (time events) for each resolvers section
  *  - the datagram layer (network IO events) for each nameserver
- * It takes one argument:
- *  - close_first takes 2 values: 0 or 1. If 1, the connection is closed first.
  * returns:
  *  0 in case of error
  *  1 when no error
  */
-int dns_init_resolvers(int close_socket)
+int dns_init_resolvers(void)
 {
 	struct dns_resolvers *curr_resolvers;
 	struct dns_nameserver *curnameserver;
@@ -963,19 +961,7 @@ int dns_init_resolvers(int close_socket)
 		curr_resolvers->t = t;
 
 		list_for_each_entry(curnameserver, &curr_resolvers->nameserver_list, list) {
-		        dgram = NULL;
-
-			if (close_socket == 1) {
-				if (curnameserver->dgram) {
-					close(curnameserver->dgram->t.sock.fd);
-					memset(curnameserver->dgram, '\0', sizeof(*dgram));
-					dgram = curnameserver->dgram;
-				}
-			}
-
-			/* allocate memory only if it has not already been allocated
-			 * by a previous call to this function */
-			if (!dgram && (dgram = calloc(1, sizeof(*dgram))) == NULL) {
+			if ((dgram = calloc(1, sizeof(*dgram))) == NULL) {
 				Alert("Starting [%s/%s] nameserver: out of memory.\n", curr_resolvers->id,
 						curnameserver->id);
 				return 0;
