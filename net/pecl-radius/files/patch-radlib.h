--- radlib.h.orig	2016-02-15 15:11:50 UTC
+++ radlib.h
@@ -133,6 +133,7 @@
 #define RAD_PORT_LIMIT			62	/* Integer */
 #define RAD_LOGIN_LAT_PORT		63	/* Integer */
 #define RAD_CONNECT_INFO		77	/* String */
+#define RAD_MSG_AUTH			80	/* String */
 
 #define RAD_NAS_IPV6_ADDRESS		95      /* IPv6 address */
 #define RAD_FRAMED_INTERFACE_ID		96      /* 8 octets */
@@ -221,14 +222,11 @@ int			 rad_config(struct rad_handle *, const char *);
 struct rad_handle	*rad_auth_open(void);
 void			 rad_close(struct rad_handle *);
 int			 rad_config(struct rad_handle *, const char *);
-int			 rad_continue_send_request(struct rad_handle *, 
-				int, int *, struct timeval *);
-int			 rad_create_request(struct rad_handle *, int);
+int			 rad_create_request(struct rad_handle *, int, bool);
 struct in_addr		 rad_cvt_addr(const void *);
 u_int32_t		 rad_cvt_int(const void *);
 char			*rad_cvt_string(const void *, size_t);
 int			 rad_get_attr(struct rad_handle *, const void **, size_t *);
-int			 rad_init_send_request(struct rad_handle *, int *, struct timeval *);
 struct rad_handle	*rad_open(void);  /* Deprecated, == rad_auth_open */
 int			 rad_put_addr(struct rad_handle *, int, struct in_addr, const struct rad_attr_options *);
 int			 rad_put_attr(struct rad_handle *, int, const void *, size_t, const struct rad_attr_options *);
@@ -242,5 +240,3 @@ int	 		 rad_salt_value(struct rad_handle *, const char
 int	 		 rad_salt_value(struct rad_handle *, const char *, size_t, struct rad_salted_value *);
 
 #endif /* _RADLIB_H_ */
-
-/* vim: set ts=8 sw=8 noet: */
