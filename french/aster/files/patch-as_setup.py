--- as_setup.py.orig	2020-10-26 15:54:27 UTC
+++ as_setup.py
@@ -45,6 +45,8 @@ import tarfile
 import compileall
 import imp
 import pprint
+import fileinput
+import string
 import distutils.sysconfig as SC
 from functools import partial
 from subprocess import Popen, PIPE
@@ -411,6 +413,7 @@ class SETUP:
             archive filename !),
          extract_as : rename content.
       """
+      from as_setup import (SYSTEM)
       self._print(self._fmt_title % _('Extraction'))
       if kargs.get('external')!=None:
          self._call_external(**kargs)
@@ -518,6 +521,88 @@ class SETUP:
       os.chdir(prev)
       if iextr_as:
          self.Clean(to_delete=path)
+
+      # Insert FreeBSD patches here
+      file2patch = os.path.join(self.workdir, self.content, 'bibc/wscript')
+      self._print('FreeBSD patch: no libdl => modify ' + file2patch)
+      for ligne in fileinput.input(file2patch, inplace=1):
+         nl = 0
+         nl = ligne.find("uselib_store='SYS', lib='dl'")
+         if nl > 0:
+            ligne =ligne.replace("self.check_cc", "# self.check_cc")
+         sys.stdout.write(ligne)
+ #     file2patch = os.path.join(self.workdir, self.content, 'bibcxx/wscript')
+ #     self._print('FreeBSD patch: explicit link with libc++ required since Gcc 4.9 => modify ' + file2patch)
+ #     for ligne in fileinput.input(file2patch, inplace=1):
+ #        nl = 0
+ #        nl = ligne.find("uselib_store='CXX', lib='stdc++'")
+ #        if nl > 0:
+ #           ligne =ligne.replace("lib='stdc++'", "lib='c++ stdc++'")
+ #        sys.stdout.write(ligne)
+      file2patch = os.path.join(self.workdir, self.content, 'waftools/scotch.py')
+      self._print('FreeBSD patch: int64_t missing => modify ' + file2patch)
+      for ligne in fileinput.input(file2patch, inplace=1):
+         nl = 0
+         nl = ligne.find('include "scotch.h"')
+         if nl > 0:
+            sys.stdout.write("#include <sys/types.h>\n")
+         nl = 0
+         nl = ligne.find("stdio.h stdlib.h scotch.h")
+         if nl > 0:
+            ligne =ligne.replace("stdlib.h", "stdlib.h sys/types.h")
+         sys.stdout.write(ligne)
+      file2patch = os.path.join(self.workdir, self.content, 'bibc/utilitai/hpalloc.c')
+      self._print('FreeBSD patch: stdlib + no mallopt => modify ' + file2patch)
+      for ligne in fileinput.input(file2patch, inplace=1):
+         nl = 0
+         nl = ligne.find('ir=mallopt')
+         if nl > 0:
+            ligne =ligne.replace('ir=mallopt', '/* ir=mallopt')
+            ligne =ligne.replace(');', '); */')
+         else:
+            nl = ligne.find("malloc.h")
+            if nl > 0:
+               ligne =ligne.replace("malloc.h", "stdlib.h")
+         sys.stdout.write(ligne)
+      system=SYSTEM({ 'verbose' : True, 'debug' : False },
+         **{'maxcmdlen' : 2**31, 'log' : self})
+      file2patch = os.path.join(self.workdir, self.content, 'waf.main')
+      self._print('FreeBSD patch: remove extraneous escape => modify waf.main')
+      for ligne in fileinput.input(file2patch, inplace=1):
+         nl = ligne.find("\main$")
+         if nl > 0:
+            ligne =ligne.replace("\main$", "main$")
+         sys.stdout.write(ligne)
+      for f2p in ('waf', 'waf.main', 'waf_variant', 'waf_std', 'waf_mpi', 'bibpyt/Macro/macr_ecre_calc_ops.py'):
+         file2patch = os.path.join(self.workdir, self.content, f2p)
+         self._print('FreeBSD patch: /bin/bash + GNU getopt => modify ' + file2patch)
+         for ligne in fileinput.input(file2patch, inplace=1):
+            nl = 0
+            nl = ligne.find("/bin/bash")
+            if nl > 0:
+               ligne =ligne.replace("/bin/bash", " %%LOCALBASE%%/bin/bash")
+            sys.stdout.write(ligne)
+         for ligne in fileinput.input(file2patch, inplace=1):
+            nl = 0
+            nl = ligne.find("getopt ")
+            if nl > 0:
+               ligne =ligne.replace("getopt ", "%%LOCALBASE%%/bin/getopt ")
+            sys.stdout.write(ligne)
+      self._print('FreeBSD patches: waf.engine and data/post_install in %s' % os.path.join(self.workdir, self.content))
+      os.system('cd ' + os.path.join(self.workdir, self.content) + ' && patch -p0 < %%WRKDIR%%/post_patches/post-patch-waf.engine')
+      os.system('cd ' + os.path.join(self.workdir, self.content) + ' && patch -p0 < %%WRKDIR%%/post_patches/post-patch-data__post_install')
+      self._print('FreeBSD patches: memory detection in bibc/utilitai/mempid.c in %s' % os.path.join(self.workdir, self.content))
+      os.system('cd ' + os.path.join(self.workdir, self.content) + ' && patch -p0 < %%WRKDIR%%/post_patches/post-patch-bibc__utilitai__mempid.c')
+      os.system('cd ' + os.path.join(self.workdir, self.content) + ' && patch -p0 < %%WRKDIR%%/post_patches/post-patch-bibc__supervis__aster_utils.c')
+      file2patch = os.path.join(self.workdir, self.content, 'waftools/mathematics.py')
+      self._print('FreeBSD patch: nproc => gnproc ' + file2patch)
+      for ligne in fileinput.input(file2patch, inplace=1):
+         nl = 0
+         nl = ligne.find("'nproc'")
+         if nl > 0:
+            ligne =ligne.replace("'nproc'", "'gnproc'")
+         sys.stdout.write(ligne)
+      # End of FreeBSD patches
 
 #-------------------------------------------------------------------------------
    def Configure(self, **kargs):
