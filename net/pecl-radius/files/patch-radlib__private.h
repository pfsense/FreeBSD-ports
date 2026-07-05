--- radlib_private.h.orig	2016-02-15 15:11:50 UTC
+++ radlib_private.h
@@ -58,15 +58,22 @@
 #define PASSSIZE	128		/* Maximum significant password chars */
 
 /* Positions of fields in RADIUS messages */
-#define POS_CODE	0		/* Message code */
-#define POS_IDENT	1		/* Identifier */
-#define POS_LENGTH	2		/* Message length */
-#define POS_AUTH	4		/* Authenticator */
-#define LEN_AUTH	16		/* Length of authenticator */
-#define POS_ATTRS	20		/* Start of attributes */
+#define POS_CODE		0	/* Message code */
+#define POS_IDENT		1	/* Identifier */
+#define POS_LENGTH		2	/* Message length */
+#define POS_AUTH		4	/* Authenticator */
+#define LEN_AUTH		16	/* Length of authenticator */
+#define POS_ATTRS		20	/* Start of attributes */
+#define POS_MSG_AUTH		20	/* Start of message authentication, if applicable */
+#define LEN_MSG_AUTH		18	/* Length of message authentication attribute */
+#define LEN_MSG_AUTH_HASH	16	/* Length of message authentication hash */
 
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
@@ -74,7 +81,8 @@ struct rad_handle {
 };
 
 struct rad_handle {
-	int		 fd;		/* Socket file descriptor */
+	int		 fd4;		/* Socket file descriptor */
+	int		 fd6;		/* Socket file descriptor */
 	struct rad_server servers[MAXSERVERS];	/* Servers to contact */
 	int		 num_servers;	/* Number of valid server entries */
 	int		 ident;		/* Current identifier value */
@@ -86,6 +94,7 @@ struct rad_handle {
 	int		 pass_len;	/* Length of cleartext password */
 	int		 pass_pos;	/* Position of scrambled password */
 	char	 	 chap_pass;	/* Have we got a CHAP_PASSWORD ? */
+	bool		 msg_auth;	/* Are we doing message authentication? */
 	unsigned char	 response[MSGSIZE];	/* Response received */
 	int		 resp_len;	/* Length of response */
 	int		 resp_pos;	/* Current position scanning attrs */
@@ -110,6 +119,4 @@ struct vendor_attribute_tag {
 	u_char attrib_data[1];
 };
 
-#endif
-
-/* vim: set ts=8 sw=8 noet: */
+#endif /* RADLIB_PRIVATE_H */
