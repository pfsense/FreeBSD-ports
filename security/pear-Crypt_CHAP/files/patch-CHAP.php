--- CHAP.php.orig	2022-08-27 20:29:05 UTC
+++ CHAP.php
@@ -199,7 +199,7 @@ class Crypt_CHAP_MSv1 extends Crypt_CHAP
         $uni = '';
         $str = (string) $str;
         for ($i = 0; $i < strlen($str); $i++) {
-            $a = ord($str{$i}) << 8;
+            $a = ord($str[$i]) << 8;
             $uni .= sprintf("%X", $a);
         }
         return pack('H*', $uni);
@@ -345,7 +345,7 @@ class Crypt_CHAP_MSv1 extends Crypt_CHAP
 
         $bin = '';
         for ($i = 0; $i < strlen($key); $i++) {
-            $bin .= sprintf('%08s', decbin(ord($key{$i})));
+            $bin .= sprintf('%08s', decbin(ord($key[$i])));
         }
 
         $str1 = explode('-', substr(chunk_split($bin, 7, '-'), 0, -1));
