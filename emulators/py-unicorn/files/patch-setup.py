# Do not bundle libunicorn.so

--- setup.py.orig	2020-02-15 00:22:32 UTC
+++ setup.py
@@ -266,11 +266,4 @@ setup(
         'Programming Language :: Python :: 3',
     ],
     requires=['ctypes'],
-    cmdclass=cmdclass,
-    zip_safe=True,
-    include_package_data=True,
-    is_pure=True,
-    package_data={
-        'unicorn': ['lib/*', 'include/unicorn/*']
-    }
 )
