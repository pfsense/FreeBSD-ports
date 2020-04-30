--- browser.c.orig	2019-11-11 04:00:36 UTC
+++ browser.c
@@ -86,6 +86,12 @@ static int browser_compare_subject (const void *a, con
   struct folder_file *pa = (struct folder_file *) a;
   struct folder_file *pb = (struct folder_file *) b;
 
+  /* Always keep '..' in first menu position */
+  if (!strncmp(pa->display_name, "..", 2))
+	  return(-1);
+  if (!strncmp(pb->display_name, "..", 2))
+	  return(1);
+
   int r = mutt_strcoll (pa->display_name, pb->display_name);
 
   return ((BrowserSort & SORT_REVERSE) ? -r : r);
@@ -96,6 +102,12 @@ static int browser_compare_date (const void *a, const 
   struct folder_file *pa = (struct folder_file *) a;
   struct folder_file *pb = (struct folder_file *) b;
 
+  /* Always keep '..' in first menu position */
+  if (!strncmp(pa->display_name, "..", 2))
+	  return(-1);
+  if (!strncmp(pb->display_name, "..", 2))
+	  return(1);
+
   int r = pa->mtime - pb->mtime;
 
   return ((BrowserSort & SORT_REVERSE) ? -r : r);
@@ -105,6 +117,12 @@ static int browser_compare_size (const void *a, const 
 {
   struct folder_file *pa = (struct folder_file *) a;
   struct folder_file *pb = (struct folder_file *) b;
+
+  /* Always keep '..' in first menu position */
+  if (!strncmp(pa->display_name, "..", 2))
+	  return(-1);
+  if (!strncmp(pb->display_name, "..", 2))
+	  return(1);
 
   int r = pa->size - pb->size;
 
