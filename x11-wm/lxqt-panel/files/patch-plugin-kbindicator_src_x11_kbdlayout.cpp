--- plugin-kbindicator/src/x11/kbdlayout.cpp.orig	2020-04-24 02:39:01 UTC
+++ plugin-kbindicator/src/x11/kbdlayout.cpp
@@ -245,10 +245,10 @@ class X11Kbd: public QAbstractNativeEventFilter (priva
         static LangInfo def{QStringLiteral("Unknown"), QStringLiteral("??"), QStringLiteral("None")};
         static QHash<QString, LangInfo> names;
         if (names.empty()){
-            if(QFile::exists(QStringLiteral("/usr/share/X11/xkb/rules/evdev.xml"))){
+            if(QFile::exists(QStringLiteral("/usr/local/share/X11/xkb/rules/evdev.xml"))){
                 QDomDocument doc;
 
-                QFile file(QStringLiteral("/usr/share/X11/xkb/rules/evdev.xml"));
+                QFile file(QStringLiteral("/usr/local/share/X11/xkb/rules/evdev.xml"));
                 if (file.open(QIODevice::ReadOnly)){
                     if (doc.setContent(&file)) {
                         QDomElement docElem = doc.documentElement();
