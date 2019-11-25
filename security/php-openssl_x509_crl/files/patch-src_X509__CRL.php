--- src/X509_CRL.php.orig	2017-05-22 07:54:44 UTC
+++ src/X509_CRL.php
@@ -72,8 +72,6 @@ class X509_CRL
 		if($ca_pkey_details === false)
 			return false;
 		$ca_pkey_type = $ca_pkey_details['type'];
-		if($ca_pkey_type == OPENSSL_KEYTYPE_EC || $ca_pkey_type == -1)
-			return false;
 		if(!in_array($ca_pkey_type, $algs_cipher))
 			return false;
 		
