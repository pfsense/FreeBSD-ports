--- src/OID.php.orig	2017-05-22 07:54:44 UTC
+++ src/OID.php
@@ -39,6 +39,13 @@ class OID
 					default:
 						return false;
 				}
+			case OPENSSL_KEYTYPE_EC:
+				switch($digest) {
+					case OPENSSL_ALGO_SHA1:
+						return self::getOIDFromName('ecdsa-with-SHA1');
+					default:
+						return false;
+				}
 			case OPENSSL_KEYTYPE_DSA:
 				switch($digest) {
 					case OPENSSL_ALGO_SHA1:
@@ -100,6 +107,11 @@ class OID
 		"1.2.840.113549.1.1.3" => "md4withRSAEncryption",
 		"1.2.840.113549.1.1.4" => "md5withRSAEncryption",
 		"1.2.840.113549.1.1.5" => "sha1withRSAEncryption",
+		//ec
+		"1.2.840.10045.4.1" => "ecdsa-with-SHA1",
+		"1.2.840.10045.4.3.2" => "ecdsa-with-sha256",
+		"1.2.840.10045.4.3.3" => "ecdsa-with-sha384",
+		"1.2.840.10045.4.3.4" => "ecdsa-with-sha512",
 		//Diffie-Hellman
 		"1.2.840.10046.2.1" => "dhPublicNumber",
 		
