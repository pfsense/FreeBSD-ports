--- setup.sh.orig	2021-03-07 18:10:32 UTC
+++ setup.sh
@@ -102,19 +102,7 @@ echo "Webmin uses separate directories for configurati
 echo "Unless you want to run multiple versions of Webmin at the same time"
 echo "you can just accept the defaults."
 echo ""
-printf "Config file directory [/etc/webmin]: "
-if [ "$config_dir" = "" ]; then
-	read config_dir
-fi
-if [ "$config_dir" = "" ]; then
-	config_dir=/etc/webmin
-fi
-abspath=`echo $config_dir | grep "^/"`
-if [ "$abspath" = "" ]; then
-	echo "Config directory must be an absolute path"
-	echo ""
-	exit 2
-fi
+config_dir=/usr/local/etc/webmin
 if [ ! -d $config_dir ]; then
 	mkdir $config_dir;
 	if [ $? != 0 ]; then
@@ -209,12 +197,12 @@ else
 	fi
 
 	# Ask for log directory
-	printf "Log file directory [/var/webmin]: "
+	printf "Log file directory [/var/log/webmin]: "
 	if [ "$var_dir" = "" ]; then
 		read var_dir
 	fi
 	if [ "$var_dir" = "" ]; then
-		var_dir=/var/webmin
+		var_dir=/var/log/webmin
 	fi
 	abspath=`echo $var_dir | grep "^/"`
 	if [ "$abspath" = "" ]; then
@@ -242,7 +230,9 @@ else
 	echo "Webmin is written entirely in Perl. Please enter the full path to the"
 	echo "Perl 5 interpreter on your system."
 	echo ""
-	if [ -x /usr/bin/perl ]; then
+	if [ -x %%PERL%% ]; then
+		perldef=%%PERL%%
+	elif [ -x /usr/bin/perl ]; then
 		perldef=/usr/bin/perl
 	elif [ -x /usr/local/bin/perl ]; then
 		perldef=/usr/local/bin/perl
@@ -443,6 +433,7 @@ else
 		fi
 	fi
 
+	atboot=0
 	# Ask whether to run at boot time
 	if [ "$atboot" = "" ]; then
 		if echo "$os_type" | grep  -q "\-linux$"; then
@@ -594,6 +585,7 @@ EOF
 	fi
 fi
 
+noperlpath="yes"
 if [ "$noperlpath" = "" ]; then
 	echo "Inserting path to perl into scripts.."
 	(find "$wadir" -name '*.cgi' -print ; find "$wadir" -name '*.pl' -print) | $perl "$wadir/perlpath.pl" $perl -
@@ -604,7 +596,6 @@ fi
 echo "Creating start and stop scripts.."
 rm -f $config_dir/stop $config_dir/start $config_dir/restart $config_dir/reload
 echo "#!/bin/sh" >>$config_dir/start
-echo "echo Starting Webmin server in $wadir" >>$config_dir/start
 echo "trap '' 1" >>$config_dir/start
 echo "LANG=" >>$config_dir/start
 echo "export LANG" >>$config_dir/start
@@ -763,6 +754,7 @@ if [ "$?" != "0" ]; then
 	echo passdelay=1 >> $config_dir/miniserv.conf
 fi
 
+nouninstall="yes"
 if [ "$nouninstall" = "" ]; then
 	echo "Creating uninstall script $config_dir/uninstall.sh .."
 	cat >$config_dir/uninstall.sh <<EOF
@@ -800,6 +792,7 @@ for f in miniserv.conf miniserv.pem miniserv.users; do
 	chmod -R og-rw $config_dir/$f
 done
 chmod +r $config_dir/version
+nochown="yes"
 if [ "$nochown" = "" ]; then
 	# Make program directory non-world-writable, but executable
 	chown -R root "$wadir"
@@ -852,6 +845,7 @@ if [ -r "$srcdir/setup-post.sh" ]; then
 	. "$srcdir/setup-post.sh"
 fi
 
+nostart="yes"
 if [ "$nostart" = "" ]; then
 	if [ "$inetd" != "1" ]; then
 		echo "Attempting to start Webmin mini web server.."
