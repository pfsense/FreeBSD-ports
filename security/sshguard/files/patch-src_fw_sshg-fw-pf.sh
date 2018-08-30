--- src/fw/sshg-fw-pf.sh.orig	2018-08-30 14:05:05 UTC
+++ src/fw/sshg-fw-pf.sh
@@ -2,20 +2,26 @@
 # sshg-fw-pf
 # This file is part of SSHGuard.
 
+if [ "$4" = "380" ]; then
+	table="webConfiguratorlockout"
+else
+	table="sshguard"
+fi
+
 fw_init() {
-    pfctl -q -t sshguard -T show > /dev/null
+    pfctl -q -t $table -T show > /dev/null
 }
 
 fw_block() {
-    pfctl -q -k $1 -t sshguard -T add $1/$3
+    pfctl -q -k $1 -t $table -T add $1/$3
 }
 
 fw_release() {
-    pfctl -q -t sshguard -T del $1/$3
+    pfctl -q -t $table -T del $1/$3
 }
 
 fw_flush() {
-    pfctl -q -t sshguard -T flush
+    pfctl -q -t $table -T flush
 }
 
 fw_fin() {
