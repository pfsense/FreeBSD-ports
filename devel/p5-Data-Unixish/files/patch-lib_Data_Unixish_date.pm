--- lib/Data/Unixish/date.pm.orig	2019-10-26 02:14:22 UTC
+++ lib/Data/Unixish/date.pm
@@ -2,7 +2,7 @@ package Data::Unixish::date;
 
 use 5.010;
 use strict;
-use syntax 'each_on_array'; # to support perl < 5.12
+#use syntax 'each_on_array'; # to support perl < 5.12
 use warnings;
 #use Log::Any '$log';
 use POSIX qw(strftime);
