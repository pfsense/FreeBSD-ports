--- pythonmod/pythonmod.c.orig	2023-01-12 08:18:31 UTC
+++ pythonmod/pythonmod.c
@@ -3,6 +3,7 @@
  *
  * Copyright (c) 2009, Zdenek Vasicek (vasicek AT fit.vutbr.cz)
  *                     Marek Vavrusa  (xvavru00 AT stud.fit.vutbr.cz)
+ * Copyright (c) 2022, Rubicon Communications, LLC (Netgate)
  *
  * This software is open source.
  *
@@ -252,6 +253,16 @@ cleanup:
 	Py_XDECREF(exc_tb);
 }
 
+/* we only want to unwind python once */
+void pythonmod_atexit(void)
+{
+   assert(py_mod_count == 0);
+   assert(maimthr != NULL);
+   
+   PyEval_RestoreThread(mainthr);
+   Py_Finalize();
+}
+
 int pythonmod_init(struct module_env* env, int id)
 {
    int py_mod_idx = py_mod_count++;
@@ -310,6 +321,9 @@ int pythonmod_init(struct module_env* env, int id)
 #endif
       SWIG_init();
       mainthr = PyEval_SaveThread();
+      
+      /* XXX: register callback to unwind Python at exit */
+      atexit(pythonmod_atexit);
    }
 
    gil = PyGILState_Ensure();
@@ -525,17 +539,18 @@ void pythonmod_deinit(struct module_env* env, int id)
       Py_XDECREF(pe->data);
       PyGILState_Release(gil);
 
-      if(--py_mod_count==0) {
-         PyEval_RestoreThread(mainthr);
-         Py_Finalize();
-         mainthr = NULL;
-      }
+      py_mod_count--;
    }
+
    pe->fname = NULL;
    free(pe);
 
    /* Module is deallocated in Python */
    env->modinfo[id] = NULL;
+
+   /* iterate over all possible callback types and clean up each in turn */
+   for (int cbtype = 0; cbtype < inplace_cb_types_total; cbtype++)
+      inplace_cb_delete(env, cbtype, id);
 }
 
 void pythonmod_inform_super(struct module_qstate* qstate, int id, struct module_qstate* super)
