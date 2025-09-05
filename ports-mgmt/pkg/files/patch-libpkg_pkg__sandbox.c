--- libpkg/pkg_sandbox.c.orig	2025-08-05 06:16:40 UTC
+++ libpkg/pkg_sandbox.c
@@ -34,6 +34,10 @@
 #include <sys/wait.h>
 #include <sys/socket.h>
 
+#if __FreeBSD_version >= 1500061
+#include <sys/sysctl.h>
+#endif
+
 #ifdef HAVE_CAPSICUM
 #include <sys/capsicum.h>
 #endif
@@ -226,13 +230,20 @@ pkg_drop_privileges(void)
 void
 pkg_drop_privileges(void)
 {
+#if __FreeBSD_version >= 1500061
+	int osver;
+	size_t buflen = sizeof(osver);
+#endif
 	struct passwd *nobody;
 
 	if (geteuid() == 0) {
 		nobody = getpwnam("nobody");
 		if (nobody == NULL)
 			errx(EXIT_FAILURE, "Unable to drop privileges: no 'nobody' user");
-		setgroups(1, &nobody->pw_gid);
+#if __FreeBSD_version >= 1500061
+		if (sysctlbyname("kern.osreldate", &osver, &buflen, NULL, 0) != 0 && osver >= 1500061)
+#endif
+			setgroups(1, &nobody->pw_gid);
 		/* setgid also sets egid and setuid also sets euid */
 		if (setgid(nobody->pw_gid) == -1)
 			err(EXIT_FAILURE, "Unable to setgid");
