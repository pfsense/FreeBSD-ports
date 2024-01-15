--- backends/smt2/smtio.py.orig	2023-01-04 19:51:15 UTC
+++ backends/smt2/smtio.py
@@ -182,9 +182,9 @@ class SmtIo:
                 self.noincr = True
 
             if self.noincr:
-                self.popen_vargs = ['yices-smt2'] + self.solver_opts
+                self.popen_vargs = ['yices_smt2'] + self.solver_opts
             else:
-                self.popen_vargs = ['yices-smt2', '--incremental'] + self.solver_opts
+                self.popen_vargs = ['yices_smt2', '--incremental'] + self.solver_opts
             if self.timeout != 0:
                 self.popen_vargs.append('-t')
                 self.popen_vargs.append('%d' % self.timeout);
