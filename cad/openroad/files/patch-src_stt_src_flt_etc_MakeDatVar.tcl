--- src/stt/src/flt/etc/MakeDatVar.tcl.orig	2022-02-10 04:38:37 UTC
+++ src/stt/src/flt/etc/MakeDatVar.tcl
@@ -32,7 +32,7 @@ close $var_stream
 set b64_file "[file rootname $dat_file].b64"
 set b64_file2 "[file rootname $dat_file].tr"
 
-exec base64 -i $dat_file > $b64_file
+exec base64 $dat_file > $b64_file
 # strip trailing \n from base64 file
 exec tr -d "\n" <$b64_file >$b64_file2
 exec cat < $b64_file2 >> $var_file
