commit e74dd1d96753b0538192143adf58d04fcd3b242b
Author: Matthias Gerstner <mgerstner@suse.de>
Date:   Fri Aug 14 22:14:36 2020 -0700

    Correct multiple security issues that are present if the tcsd
    is started by root instead of the tss user.
    
    Patch fixes the following 3 CVEs:
    
    CVE-2020-24332
    If the tcsd daemon is started with root privileges,
    the creation of the system.data file is prone to symlink attacks
    
    CVE-2020-24330
    If the tcsd daemon is started with root privileges,
    it fails to drop the root gid after it is no longer needed
    
    CVE-2020-24331
    If the tcsd daemon is started with root privileges,
    the tss user has read and write access to the /etc/tcsd.conf file
    
    Authored-by: Matthias Gerstner <mgerstner@suse.de>
    Signed-off-by: Debora Velarde Babb <debora@linux.ibm.com>

diff --git src/tcs/ps/tcsps.c src/tcs/ps/tcsps.c
index e47154b..85d45a9 100644
--- src/tcs/ps/tcsps.c
+++ src/tcs/ps/tcsps.c
@@ -72,7 +72,7 @@ get_file()
 	}
 
 	/* open and lock the file */
-	system_ps_fd = open(tcsd_options.system_ps_file, O_CREAT|O_RDWR, 0600);
+	system_ps_fd = open(tcsd_options.system_ps_file, O_CREAT|O_RDWR|O_NOFOLLOW, 0600);
 	if (system_ps_fd < 0) {
 		LogError("system PS: open() of %s failed: %s",
 				tcsd_options.system_ps_file, strerror(errno));
diff --git src/tcsd/svrside.c src/tcsd/svrside.c
index 1ae1636..1c12ff3 100644
--- src/tcsd/svrside.c
+++ src/tcsd/svrside.c
@@ -473,6 +473,7 @@ main(int argc, char **argv)
 		}
 		return TCSERR(TSS_E_INTERNAL_ERROR);
 	}
+	setgid(pwd->pw_gid);
 	setuid(pwd->pw_uid);
 #endif
 #endif
diff --git src/tcsd/tcsd_conf.c src/tcsd/tcsd_conf.c
index a31503d..ea8ea13 100644
--- src/tcsd/tcsd_conf.c
+++ src/tcsd/tcsd_conf.c
@@ -743,7 +743,7 @@ conf_file_init(struct tcsd_config *conf)
 #ifndef SOLARIS
 	struct group *grp;
 	struct passwd *pw;
-	mode_t mode = (S_IRUSR|S_IWUSR);
+	mode_t mode = (S_IRUSR|S_IWUSR|S_IRGRP);
 #endif /* SOLARIS */
 	TSS_RESULT result;
 
@@ -798,15 +798,15 @@ conf_file_init(struct tcsd_config *conf)
 	}
 
 	/* make sure user/group TSS owns the conf file */
-	if (pw->pw_uid != stat_buf.st_uid || grp->gr_gid != stat_buf.st_gid) {
+	if (stat_buf.st_uid != 0 || grp->gr_gid != stat_buf.st_gid) {
 		LogError("TCSD config file (%s) must be user/group %s/%s", tcsd_config_file,
-				TSS_USER_NAME, TSS_GROUP_NAME);
+				"root", TSS_GROUP_NAME);
 		return TCSERR(TSS_E_INTERNAL_ERROR);
 	}
 
-	/* make sure only the tss user can manipulate the config file */
+	/* make sure only the tss user can read (but not manipulate) the config file */
 	if (((stat_buf.st_mode & 0777) ^ mode) != 0) {
-		LogError("TCSD config file (%s) must be mode 0600", tcsd_config_file);
+		LogError("TCSD config file (%s) must be mode 0640", tcsd_config_file);
 		return TCSERR(TSS_E_INTERNAL_ERROR);
 	}
 #endif /* SOLARIS */
