--- libpkg/pkg_sandbox.c.orig	2025-08-05 06:16:40 UTC
+++ libpkg/pkg_sandbox.c
@@ -33,6 +33,7 @@
 #include <sys/types.h>
 #include <sys/wait.h>
 #include <sys/socket.h>
+#include <sys/sysctl.h>
 
 #ifdef HAVE_CAPSICUM
 #include <sys/capsicum.h>
@@ -226,13 +227,16 @@ pkg_drop_privileges(void)
 void
 pkg_drop_privileges(void)
 {
+	int osver;
+	size_t buflen = sizeof(osver);
 	struct passwd *nobody;
 
 	if (geteuid() == 0) {
 		nobody = getpwnam("nobody");
 		if (nobody == NULL)
 			errx(EXIT_FAILURE, "Unable to drop privileges: no 'nobody' user");
-		setgroups(1, &nobody->pw_gid);
+		if (sysctlbyname("kern.osreldate", &osver, &buflen, NULL, 0) != 0 && osver >= 1500061)
+			setgroups(1, &nobody->pw_gid);
 		/* setgid also sets egid and setuid also sets euid */
 		if (setgid(nobody->pw_gid) == -1)
 			err(EXIT_FAILURE, "Unable to setgid");
