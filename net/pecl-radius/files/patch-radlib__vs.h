--- radlib_vs.h.orig	2016-02-15 15:11:50 UTC
+++ radlib_vs.h
@@ -83,5 +83,3 @@ int	rad_put_vendor_string(struct rad_handle *, int, in
 int	rad_demangle_mppe_key(struct rad_handle *, const void *, size_t, u_char *, size_t *);
 
 #endif /* _RADLIB_VS_H_ */
-
-/* vim: set ts=8 sw=8 noet: */
