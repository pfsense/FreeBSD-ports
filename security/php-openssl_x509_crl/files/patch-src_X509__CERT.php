--- src/X509_CERT.php.orig	2023-09-07 16:20:20 UTC
+++ src/X509_CERT.php
@@ -90,7 +90,7 @@ class X509_CERT
 		$ret->content['keyIdentifier']->setType(0, false, ASN1_CLASSTYPE_CONTEXT);
 		
 		//Copy subject
-		$subject = $cert_root->content[0]->content[$is_v1 ? 4 : 5];
+		$subject = $cert_root->content[0]->content[$is_v1 ? 2 : 3];
 		
 		//Write into authorityCertIssuer ([4] EXPLICIT Name)
 		$ret->content['authorityCertIssuer'] = new ASN1_SEQUENCE; //it's GeneralNames
