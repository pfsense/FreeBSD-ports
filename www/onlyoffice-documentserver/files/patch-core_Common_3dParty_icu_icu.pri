--- core/Common/3dParty/icu/icu.pri.orig	2021-09-30 12:13:32 UTC
+++ core/Common/3dParty/icu/icu.pri
@@ -8,6 +8,13 @@ core_windows {
     }
 
     LIBS        += -L$$PWD/$$CORE_BUILDS_PLATFORM_PREFIX/build -licuuc
+}
+
+core_freebsd {
+    INCLUDEPATH += %%LOCALBASE%%/include
+
+    LIBS        += %%LOCALBASE%%/lib/libicuuc.so
+    LIBS        += %%LOCALBASE%%/lib/libicudata.so
 }
 
 core_linux {
