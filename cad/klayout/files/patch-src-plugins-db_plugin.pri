--- src/plugins/db_plugin.pri.orig	2021-07-23 08:00:26 UTC
+++ src/plugins/db_plugin.pri
@@ -5,7 +5,7 @@ TEMPLATE = lib
 
 INCLUDEPATH += $$DB_INC $$TL_INC $$GSI_INC $$PWD/common
 DEPENDPATH += $$DB_INC $$TL_INC $$GSI_INC $$PWD/common
-LIBS += -L$$DESTDIR/.. -lklayout_db -lklayout_tl -lklayout_gsi
+LIBS += $$DESTDIR/../libklayout_db.so $$DESTDIR/../libklayout_tl.so $$DESTDIR/../libklayout_gsi.so
 
 DEFINES += MAKE_DB_PLUGIN_LIBRARY
 
@@ -14,13 +14,13 @@ win32 {
   dlltarget.path = $$PREFIX/db_plugins
   INSTALLS += dlltarget
 
-  # to avoid the major version being appended to the dll name - in this case -lxyz won't link it again
+  # to avoid the major version being appended to the dll name - in this case $$DESTDIR/../libxyz won't link it again
   # because the library is called xyx0.dll.
   CONFIG += skip_target_version_ext
 
 } else {
 
-  target.path = $$PREFIX/db_plugins
+  target.path = $$shell_path($(INSTALLROOT)$$PREFIX/lib/klayout/db_plugins)
   INSTALLS += target
 
 }
