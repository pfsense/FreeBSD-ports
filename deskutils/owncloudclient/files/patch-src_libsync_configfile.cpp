--- src/libsync/configfile.cpp.orig	2020-12-21 16:16:36 UTC
+++ src/libsync/configfile.cpp
@@ -526,11 +526,22 @@ bool ConfigFile::skipUpdateCheck(const QString &connec
     if (connection.isEmpty())
         con = defaultConnection();
 
+#if 0
     QVariant fallback = getValue(skipUpdateCheckC(), con, false);
+#else
+    QVariant fallback = getValue(skipUpdateCheckC(), con, true);
+#endif
     fallback = getValue(skipUpdateCheckC(), QString(), fallback);
 
     QVariant value = getPolicySetting(skipUpdateCheckC(), fallback);
+#if 0
     return value.toBool();
+#else
+    if ( !value.toBool() )
+        qDebug() << "FreeBSD package disabled the UpdateCheck mechanism.";
+
+    return true;
+#endif
 }
 
 void ConfigFile::setSkipUpdateCheck(bool skip, const QString &connection)
