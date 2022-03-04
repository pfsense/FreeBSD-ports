--- src/gtkcompletionline.cc.orig	2003-11-16 10:55:07 UTC
+++ src/gtkcompletionline.cc
@@ -39,6 +39,8 @@ static int on_key_press_handler = 0;
 
 /* GLOBALS */
 
+GtkType type = 0;
+
 /* signals */
 enum {
   UNIQUE,
@@ -76,14 +78,13 @@ static gboolean
 on_key_press(GtkCompletionLine *cl, GdkEventKey *event, gpointer data);
 
 /* get_type */
-guint gtk_completion_line_get_type(void)
+GtkType gtk_completion_line_get_type(void)
 {
-  static guint type = 0;
   if (type == 0)
   {
     GtkTypeInfo type_info =
     {
-      "GtkCompletionLine",
+      (gchar *)"GtkCompletionLine",
       sizeof(GtkCompletionLine),
       sizeof(GtkCompletionLineClass),
       (GtkClassInitFunc)gtk_completion_line_class_init,
@@ -551,10 +552,10 @@ parse_tilda(GtkCompletionLine *object)
 {
   string text = gtk_entry_get_text(GTK_ENTRY(object));
   gint where = (gint)text.find("~");
-  if (where != string::npos) {
+  if (where != (gint)string::npos) {
     if ((where > 0) && (text[where - 1] != ' '))
       return 0;
-    if (where < text.size() - 1 && text[where + 1] != '/') {
+    if (where < (gint)text.size() - 1 && text[where + 1] != '/') {
       // FIXME: Parse another user's home
     } else {
       string home = g_get_home_dir();
