--- src/common/attack.h.orig	2020-06-23 23:01:26 UTC
+++ src/common/attack.h
@@ -45,6 +45,7 @@ enum service {
     SERVICES_CLF_UNAUTH     = 350,  //< HTTP 401 in common log format
     SERVICES_CLF_PROBES     = 360,  //< probes for common web services
     SERVICES_CLF_LOGIN_URL  = 370,  //< CMS framework logins in common log format
+    SERVICES_PFSENSE        = 380,  //< pfSense UI login
     SERVICES_OPENVPN        = 400,  //< OpenVPN
     SERVICES_GITEA          = 500,  //< Gitea
 };
