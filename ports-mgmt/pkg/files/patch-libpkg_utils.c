--- libpkg/utils.c.orig	2025-12-23 10:23:55 UTC
+++ libpkg/utils.c
@@ -1146,14 +1146,15 @@ get_uid_from_uname(const char *uname)
 	static struct passwd pwent;
 	struct passwd *result;
 	int err;
+	const char *testuname = uname ? uname : "";
 
-	if (pwent.pw_name != NULL && STREQ(uname, pwent.pw_name))
+	if (pwent.pw_name != NULL && STREQ(testuname, pwent.pw_name))
 		goto out;
 	pwent.pw_name = NULL;
-	err = getpwnam_r(uname, &pwent, user_buffer, sizeof(user_buffer),
+	err = getpwnam_r(testuname, &pwent, user_buffer, sizeof(user_buffer),
 	    &result);
 	if (err != 0) {
-		pkg_emit_errno("getpwnam_r", uname);
+		pkg_emit_errno("getpwnam_r", testuname);
 		return (0);
 	}
 	if (result == NULL)
@@ -1169,14 +1170,15 @@ get_gid_from_gname(const char *gname)
 	static struct group grent;
 	struct group *result;
 	int err;
+	const char *testgname = gname ? gname : "";
 
-	if (grent.gr_name != NULL && STREQ(gname, grent.gr_name))
+	if (grent.gr_name != NULL && STREQ(testgname, grent.gr_name))
 		goto out;
 	grent.gr_name = NULL;
-	err = getgrnam_r(gname, &grent, group_buffer, sizeof(group_buffer),
+	err = getgrnam_r(testgname, &grent, group_buffer, sizeof(group_buffer),
 	    &result);
 	if (err != 0) {
-		pkg_emit_errno("getgrnam_r",gname);
+		pkg_emit_errno("getgrnam_r", testgname);
 		return (0);
 	}
 	if (result == NULL)
