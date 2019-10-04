--- src/common/attack.h.orig	2019-05-24 16:16:43 UTC
+++ src/common/attack.h
@@ -45,6 +45,7 @@ enum service {
     SERVICES_CLF_UNAUTH     = 350,  //< HTTP 401 in common log format
     SERVICES_CLF_PROBES     = 360,  //< probes for common web services
     SERVICES_CLF_WORDPRESS  = 370,  //< WordPress logins in common log format
+    SERVICES_PFSENSE        = 380,  //< pfSense UI login
     SERVICES_OPENVPN        = 400,  //< OpenVPN
     SERVICES_GITEA          = 500,  //< Gitea
 };
