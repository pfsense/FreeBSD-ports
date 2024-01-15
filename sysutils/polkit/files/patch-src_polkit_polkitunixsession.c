FreeBSD ConsoleKit is patched to return proper IDs instead D-Bus paths, so
adapt Polkit to this case.

--- src/polkit/polkitunixsession.c.orig	2023-07-28 12:34:38 UTC
+++ src/polkit/polkitunixsession.c
@@ -364,6 +364,7 @@ polkit_unix_session_exists_sync (PolkitSubject   *subj
   PolkitUnixSession *session = POLKIT_UNIX_SESSION (subject);
   GDBusConnection *connection;
   GVariant *result;
+  const gchar* session_path = NULL;
   gboolean ret;
 
   ret = FALSE;
@@ -372,9 +373,12 @@ polkit_unix_session_exists_sync (PolkitSubject   *subj
   if (connection == NULL)
     goto out;
 
+  if (strncmp (session->session_id, "/org/freedesktop/ConsoleKit", strlen ("/org/freedesktop/ConsoleKit") ))
+    session_path = g_build_path("/", "/org/freedesktop/ConsoleKit", session->session_id, NULL);
+
   result = g_dbus_connection_call_sync (connection,
                                         "org.freedesktop.ConsoleKit",           /* name */
-                                        session->session_id,                    /* object path */
+                                        session_path ? session_path : session->session_id,                    /* object path */
                                         "org.freedesktop.ConsoleKit.Session",   /* interface name */
                                         "GetUser",                              /* method */
                                         NULL, /* parameters */
@@ -383,6 +387,7 @@ polkit_unix_session_exists_sync (PolkitSubject   *subj
                                         -1,
                                         cancellable,
                                         error);
+  g_free (session_path);
   if (result == NULL)
     goto out;
 
@@ -472,6 +477,7 @@ polkit_unix_session_initable_init (GInitable     *init
   PolkitUnixSession *session = POLKIT_UNIX_SESSION (initable);
   GDBusConnection *connection;
   GVariant *result;
+  const gchar* session_path;
   gboolean ret;
 
   connection = NULL;
@@ -502,7 +508,8 @@ polkit_unix_session_initable_init (GInitable     *init
   if (result == NULL)
     goto out;
 
-  g_variant_get (result, "(o)", &session->session_id);
+  g_variant_get (result, "(&o)", &session_path);
+  session->session_id = g_path_get_basename (session_path);
   g_variant_unref (result);
 
   ret = TRUE;
