--- tensorflow/tools/pip_package/build_pip_package.py.orig	2025-01-01 00:00:00 UTC
+++ tensorflow/tools/pip_package/build_pip_package.py
@@ -397,6 +397,12 @@
   numpy_include_dir = "external/pypi_numpy/site-packages/numpy/_core/include"
   if not os.path.exists(numpy_include_dir):
     numpy_include_dir = "external/pypi_numpy/site-packages/numpy/core/include"
+  if not os.path.exists(numpy_include_dir):
+    _pylib = os.environ.get("PYTHON_LIB_PATH", "")
+    if _pylib:
+      numpy_include_dir = os.path.join(_pylib, "numpy", "_core", "include")
+      if not os.path.exists(numpy_include_dir):
+        numpy_include_dir = os.path.join(_pylib, "numpy", "core", "include")
   shutil.copytree(
       numpy_include_dir,
       os.path.join(dst_dir, "numpy_include"),
@@ -404,5 +410,10 @@
   if is_windows():
     path = "external/python_*/include"
   else:
     path = "external/python_*/include/python*"
-  shutil.copytree(glob.glob(path)[0], os.path.join(dst_dir, "python_include"))
+  _python_includes = glob.glob(path)
+  if not _python_includes:
+    import sysconfig
+    _inc = sysconfig.get_path('include')
+    if _inc and os.path.exists(_inc):
+      _python_includes = [_inc]
+  shutil.copytree(_python_includes[0], os.path.join(dst_dir, "python_include"))
