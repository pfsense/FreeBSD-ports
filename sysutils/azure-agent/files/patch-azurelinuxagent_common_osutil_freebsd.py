--- azurelinuxagent/common/osutil/freebsd.py.orig	2019-11-07 00:36:56 UTC
+++ azurelinuxagent/common/osutil/freebsd.py
@@ -36,14 +36,16 @@ class FreeBSDOSUtil(DefaultOSUtil):
         self.jit_enabled = True
 
     def set_hostname(self, hostname):
-        rc_file_path = '/etc/rc.conf'
-        conf_file = fileutil.read_file(rc_file_path).split("\n")
-        textutil.set_ini_config(conf_file, "hostname", hostname)
-        fileutil.write_file(rc_file_path, "\n".join(conf_file))
-        shellutil.run("hostname {0}".format(hostname), chk_err=False)
+        #rc_file_path = '/etc/rc.conf'
+        #conf_file = fileutil.read_file(rc_file_path).split("\n")
+        #textutil.set_ini_config(conf_file, "hostname", hostname)
+        #fileutil.write_file(rc_file_path, "\n".join(conf_file))
+        #shellutil.run("hostname {0}".format(hostname), chk_err=False)
+        shellutil.run("/usr/local/sbin/set-pfsense-hostname {0}".format(hostname), chk_err=False)
 
     def restart_ssh_service(self):
-        return shellutil.run('service sshd restart', chk_err=False)
+        #return shellutil.run('service sshd restart', chk_err=False)
+        return shellutil.run("/usr/local/sbin/pfSctl -c 'service restart sshd'", chk_err=False)
 
     def useradd(self, username, expiration=None, comment=None):
         """
@@ -53,10 +55,9 @@ class FreeBSDOSUtil(DefaultOSUtil):
         if userentry is not None:
             logger.warn("User {0} already exists, skip useradd", username)
             return
+        cmd = "/usr/local/sbin/add-pfsense-user {0}".format(username)
         if expiration is not None:
-            cmd = "pw useradd {0} -e {1} -m".format(username, expiration)
-        else:
-            cmd = "pw useradd {0} -m".format(username)
+            cmd += " {0}".format(expiration)
         if comment is not None:
             cmd += " -c {0}".format(comment)
         retcode, out = shellutil.run_get_output(cmd)
@@ -73,15 +74,16 @@ class FreeBSDOSUtil(DefaultOSUtil):
         self.conf_sudoer(username, remove=True)
 
     def chpasswd(self, username, password, crypt_id=6, salt_len=10):
-        if self.is_sys_user(username):
-            raise OSUtilError(("User {0} is a system user, "
-                               "will not set password.").format(username))
-        passwd_hash = textutil.gen_password_hash(password, crypt_id, salt_len)
-        cmd = "echo '{0}'|pw usermod {1} -H 0 ".format(passwd_hash, username)
-        ret, output = shellutil.run_get_output(cmd, log_cmd=False)
-        if ret != 0:
-            raise OSUtilError(("Failed to set password for {0}: {1}"
-                               "").format(username, output))
+        #if self.is_sys_user(username):
+        #    raise OSUtilError(("User {0} is a system user, "
+        #                       "will not set password.").format(username))
+        #passwd_hash = textutil.gen_password_hash(password, crypt_id, salt_len)
+        #cmd = "echo '{0}'|pw usermod {1} -H 0 ".format(passwd_hash, username)
+        #ret, output = shellutil.run_get_output(cmd, log_cmd=False)
+        #if ret != 0:
+        #    raise OSUtilError(("Failed to set password for {0}: {1}"
+        #                       "").format(username, output))
+        return shellutil.run("/usr/local/sbin/set-pfsense-password {0} '{1}'".format(username,password))
 
     def del_root_password(self):
         err = shellutil.run('pw usermod root -h -')
@@ -441,7 +443,8 @@ class FreeBSDOSUtil(DefaultOSUtil):
 
     def restart_if(self, ifname):
         # Restart dhclient only to publish hostname
-        shellutil.run("/etc/rc.d/dhclient restart {0}".format(ifname), chk_err=False)
+        #shellutil.run("/etc/rc.d/dhclient restart {0}".format(ifname), chk_err=False)
+        shellutil.run("/usr/local/sbin/pfSctl -c 'interface reconfigure {0}'".format(ifname), chk_err=False)
 
     def get_total_mem(self):
         cmd = "sysctl hw.physmem |awk '{print $2}'"
