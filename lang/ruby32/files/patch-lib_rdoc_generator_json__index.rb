--- lib/rdoc/generator/json_index.rb.orig	2017-11-27 10:45:24 UTC
+++ lib/rdoc/generator/json_index.rb
@@ -175,7 +175,7 @@ class RDoc::Generator::JsonIndex
     debug_msg "Writing gzipped search index to %s" % outfile
 
     Zlib::GzipWriter.open(outfile) do |gz|
-      gz.mtime = File.mtime(search_index_file)
+      gz.mtime = 1
       gz.orig_name = search_index_file.basename.to_s
       gz.write search_index
       gz.close
@@ -193,7 +193,7 @@ class RDoc::Generator::JsonIndex
         debug_msg "Writing gzipped file to %s" % outfile
 
         Zlib::GzipWriter.open(outfile) do |gz|
-          gz.mtime = File.mtime(dest)
+          gz.mtime = 1
           gz.orig_name = dest.basename.to_s
           gz.write data
           gz.close
