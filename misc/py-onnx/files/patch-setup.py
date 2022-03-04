--- setup.py.orig	2021-01-29 23:31:55 UTC
+++ setup.py
@@ -56,11 +56,12 @@ COVERAGE = bool(os.getenv('COVERAGE'))
 # Version
 ################################################################################
 
-try:
-    git_version = subprocess.check_output(['git', 'rev-parse', 'HEAD'],
-                                          cwd=TOP_DIR).decode('ascii').strip()
-except (OSError, subprocess.CalledProcessError):
-    git_version = None
+#try:
+#    git_version = subprocess.check_output(['git', 'rev-parse', 'HEAD'],
+#                                          cwd=TOP_DIR).decode('ascii').strip()
+#except (OSError, subprocess.CalledProcessError):
+#    git_version = None
+git_version = None
 
 with open(os.path.join(TOP_DIR, 'VERSION_NUMBER')) as version_file:
     VersionInfo = namedtuple('VersionInfo', ['version', 'git_version'])(
