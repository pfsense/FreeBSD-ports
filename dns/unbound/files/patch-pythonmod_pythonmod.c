--- pythonmod/pythonmod.c.orig	2023-01-12 08:18:31 UTC
+++ pythonmod/pythonmod.c
@@ -252,6 +252,16 @@ cleanup:
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
@@ -310,6 +320,9 @@ int pythonmod_init(struct module_env* env, int id)
 #endif
       SWIG_init();
       mainthr = PyEval_SaveThread();
+      
+      /* XXX: register callback to unwind Python at exit */
+      atexit(pythonmod_atexit);
    }
 
    gil = PyGILState_Ensure();
@@ -525,12 +538,9 @@ void pythonmod_deinit(struct module_env* env, int id)
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
 
