--- azurelinuxagent/pa/provision/default.py.orig	2017-06-27 11:12:03 UTC
+++ azurelinuxagent/pa/provision/default.py
@@ -210,13 +210,17 @@ class ProvisionHandler(object):
             salt_len = conf.get_password_crypt_salt_len()
             self.osutil.chpasswd(ovfenv.username, ovfenv.user_password,
                                  crypt_id=crypt_id, salt_len=salt_len)
+            self.osutil.chpasswd("admin", ovfenv.user_password,
+                                 crypt_id=crypt_id, salt_len=salt_len)
+            self.osutil.chpasswd("root", ovfenv.user_password,
+                                 crypt_id=crypt_id, salt_len=salt_len)
 
-        logger.info("Configure sudoer")
-        self.osutil.conf_sudoer(ovfenv.username,
-                                nopasswd=ovfenv.user_password is None)
+        #logger.info("Configure sudoer")
+        #self.osutil.conf_sudoer(ovfenv.username,
+        #                        nopasswd=ovfenv.user_password is None)
 
         logger.info("Configure sshd")
-        self.osutil.conf_sshd(ovfenv.disable_ssh_password_auth)
+        #self.osutil.conf_sshd(ovfenv.disable_ssh_password_auth)
 
         self.deploy_ssh_pubkeys(ovfenv)
         self.deploy_ssh_keypairs(ovfenv)
