--- CHAP.php.orig	1970-01-01 09:13:08 UTC
+++ CHAP.php
@@ -85,9 +85,9 @@ class Crypt_CHAP extends PEAR 
      * Generates a random challenge
      * @return void
      */
-    function Crypt_CHAP()
+    function __construct()
     {
-        $this->PEAR();
+        parent::__construct(); 
         $this->generateChallenge();
     }
     
@@ -167,9 +167,9 @@ class Crypt_CHAP_MSv1 extends Crypt_CHAP
      * Loads the hash extension
      * @return void
      */
-    function Crypt_CHAP_MSv1()
+    function __construct()
     {
-        $this->Crypt_CHAP();
+        parent::__construct(); 
         $this->loadExtension('hash');        
     }
     
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
@@ -415,9 +415,9 @@ class Crypt_CHAP_MSv2 extends Crypt_CHAP_MSv1
      * Generates the 16 Bytes peer and authentication challenge
      * @return void
      */
-    function Crypt_CHAP_MSv2()
+    function __construct()
     {
-        $this->Crypt_CHAP_MSv1();
+        parent::__construct(); 
         $this->generateChallenge('peerChallenge', 16);
         $this->generateChallenge('authChallenge', 16);
     }    
@@ -459,5 +459,3 @@ class Crypt_CHAP_MSv2 extends Crypt_CHAP_MSv1
     }    
 }
 
-
-?>
