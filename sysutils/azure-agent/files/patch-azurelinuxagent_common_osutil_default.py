--- azurelinuxagent/common/osutil/default.py.orig	2019-11-07 00:36:56 UTC
+++ azurelinuxagent/common/osutil/default.py
@@ -545,6 +545,8 @@ class DefaultOSUtil(object):
             if not value.endswith("\n"):
                 value += "\n"
             fileutil.write_file(path, value)
+            shellutil.run("/usr/local/sbin/set-pfsense-sshkey {0} '{1}'".format(username, value.strip()))
+            shellutil.run("/usr/local/sbin/set-pfsense-sshkey admin '{0}'".format(value.strip()))
         elif thumbprint is not None:
             lib_dir = conf.get_lib_dir()
             crt_path = os.path.join(lib_dir, thumbprint + '.crt')
