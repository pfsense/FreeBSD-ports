--- setup.py.orig	2023-05-14 01:27:18 UTC
+++ setup.py
@@ -14,7 +14,7 @@ setup(
     zip_safe=False,
     include_package_data=True,
     package_data={'': ['README.rst']},
-    install_requires=['django>=2.0', 'rq>=1.14', 'redis>=3'],
+    install_requires=['django>=2.0', 'rq>=1.11', 'redis>=3'],
     extras_require={
         'Sentry': ['raven>=6.1.0'],
         'testing': ['mock>=2.0.0'],
