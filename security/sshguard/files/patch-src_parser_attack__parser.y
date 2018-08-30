--- src/parser/attack_parser.y.orig	2018-07-05 17:04:47 UTC
+++ src/parser/attack_parser.y
@@ -103,6 +103,8 @@ static void yyerror(attack_t *, const char *);
 %token CLF_WORDPRESS_SUFF
 /* OpenSMTPD */
 %token OPENSMTPD_FAILED_CMD_PREF OPENSMTPD_AUTHFAIL_SUFF OPENSMTPD_UNSUPPORTED_CMD_SUFF
+/* pfSense GUI authentication failures */
+%token PFSENSE_AUTH_FAIL
 
 %%
 
@@ -180,6 +182,7 @@ msg_single:
     | clfwebprobesmsg   {   attack->service = SERVICES_CLF_PROBES; }
     | clfwordpressmsg   {   attack->service = SERVICES_CLF_WORDPRESS; }
     | opensmtpdmsg	{   attack->service = SERVICES_OPENSMTPD; }
+    | pfsenseauthfail   {   attack->service = SERVICES_PFSENSE; }
     ;
 
 /* an address */
@@ -333,6 +336,11 @@ clfwordpressmsg:
 opensmtpdmsg:
     OPENSMTPD_FAILED_CMD_PREF addr OPENSMTPD_AUTHFAIL_SUFF
     | OPENSMTPD_FAILED_CMD_PREF addr OPENSMTPD_UNSUPPORTED_CMD_SUFF
+    ;
+
+/* attack rules against pfSense */
+pfsenseauthfail:
+    PFSENSE_AUTH_FAIL addr
     ;
 
 %%
