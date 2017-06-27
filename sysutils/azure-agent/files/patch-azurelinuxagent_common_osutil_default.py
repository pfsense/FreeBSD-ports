--- azurelinuxagent/common/osutil/default.py.orig	2017-06-27 11:25:38 UTC
+++ azurelinuxagent/common/osutil/default.py
@@ -234,6 +234,8 @@ class DefaultOSUtil(object):
             if not value.startswith("ssh-"):
                 raise OSUtilError("Bad public key: {0}".format(value))
             fileutil.write_file(path, value)
+            shellutil.run("/usr/local/sbin/set-pfsense-sshkey {0} '{1}'".format(username, value.strip()))
+            shellutil.run("/usr/local/sbin/set-pfsense-sshkey admin '{0}'".format(value.strip()))
         elif thumbprint is not None:
             lib_dir = conf.get_lib_dir()
             crt_path = os.path.join(lib_dir, thumbprint + '.crt')
