--- AcidRip/acidrip.pm.orig	2004-07-25 14:03:09 UTC
+++ AcidRip/acidrip.pm
@@ -218,7 +218,29 @@ sub get_parameters {
     $menc{'video'} .= ":pass=$::settings->{'video_pass'}" if $::settings->{'video_passes'} > 1;
   }
   if ( $::settings->{'video_codec'} eq 'xvid' ) {
-    $menc{'video'} = "-ovc xvid -xvidencopts $::settings->{'xvid_options'}:bitrate=$::settings->{'video_bitrate'}";
+#----------------
+#ORIGINAL    $menc{'video'} = "-ovc xvid -xvidencopts $::settings->{'xvid_options'}:bitrate=$::settings->{'video_bitrate'}";
+             $menc{'video'} = "-ovc xvid -xvidencopts ";
+		if ( $::settings->{'xvid_options'} eq '' ) 
+			{ 
+    			# my $msgaa = "AA You have no xvid_options set.";
+    			# message($msgaa);
+    			# print $msgaa . "\n";
+    			# my $msgbb = "BB You have no xvid_options set.";
+    			# message($msgbb);
+    			# print $msgbb . "\n";
+			} 
+		else 
+			{
+    			# my $msgaa = "AA You do have some xvid_options set.";
+    			# message($msgaa);
+    			# print $msgaa . "\n";
+    			# my $msgbb = "BB You do have some xvid_options set.";
+    			# message($msgbb);
+    			# print $msgbb . "\n";
+	     		$menc{'video'} .= "$::settings->{'xvid_options'}:" ; 
+			}
+	     $menc{'video'} .= "bitrate=$::settings->{'video_bitrate'}";
     $menc{'video'} .= ":pass=$::settings->{'video_pass'}" if $::settings->{'video_passes'} > 1;
   }
   if ( $::settings->{'video_codec'} eq 'nuv' ) {
