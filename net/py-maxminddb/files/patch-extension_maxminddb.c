--- extension/maxminddb.c.orig	2023-01-09 13:42:35 UTC
+++ extension/maxminddb.c
@@ -737,6 +737,7 @@ PyMODINIT_FUNC PyInit_extension(void) {
     if (PyType_Ready(&Metadata_Type)) {
         return NULL;
     }
+    Py_INCREF(&Metadata_Type);
     PyModule_AddObject(m, "Metadata", (PyObject *)&Metadata_Type);
 
     PyObject *error_mod = PyImport_ImportModule("maxminddb.errors");
