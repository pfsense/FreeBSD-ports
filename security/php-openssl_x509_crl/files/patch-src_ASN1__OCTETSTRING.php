--- src/ASN1_OCTETSTRING.php.orig	2023-05-01 17:57:35 UTC
+++ src/ASN1_OCTETSTRING.php
@@ -22,7 +22,7 @@ class ASN1_OCTETSTRING extends ASN1
     public function __construct($str = "", $twodots = false) {
         if($str === false) {
             $this->content = array();
-        } else if(preg_match("|^[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2})+$|s", $str) /* || $twodots*/) {
+        } else if(preg_match("|^[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2})+$|s", (string) $str) /* || $twodots*/) {
             $octets = explode(':', $str);
             foreach($octets as &$v) {
                 $v = chr(hexdec($v));
