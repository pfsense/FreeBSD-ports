--- radlib_private.h.orig	2016-02-15 15:11:50 UTC
+++ radlib_private.h
@@ -66,7 +66,11 @@
 #define POS_ATTRS	20		/* Start of attributes */
 
 struct rad_server {
-	struct sockaddr_in addr;	/* Address of server */
+	union {
+		struct sockaddr_in addr4;
+		struct sockaddr_in6 addr6;
+		struct sockaddr addr;
+	} addr;				/* Address of server */
 	char		*secret;	/* Shared secret */
 	int		 timeout;	/* Timeout in seconds */
 	int		 max_tries;	/* Number of tries before giving up */
@@ -74,7 +78,8 @@ struct rad_server {
 };
 
 struct rad_handle {
-	int		 fd;		/* Socket file descriptor */
+	int		 fd4;		/* Socket file descriptor */
+	int		 fd6;		/* Socket file descriptor */
 	struct rad_server servers[MAXSERVERS];	/* Servers to contact */
 	int		 num_servers;	/* Number of valid server entries */
 	int		 ident;		/* Current identifier value */
@@ -110,6 +115,4 @@ struct vendor_attribute_tag {
 	u_char attrib_data[1];
 };
 
-#endif
-
-/* vim: set ts=8 sw=8 noet: */
+#endif /* RADLIB_PRIVATE_H */
