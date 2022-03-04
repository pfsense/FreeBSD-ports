--- src/parser/attack_parser.y.orig	2020-06-23 23:01:26 UTC
+++ src/parser/attack_parser.y
@@ -110,6 +110,8 @@ static void yyerror(attack_t *, const char *);
 %token OPENVPN_TLS_ERR_SUFF
 /* Gitea */
 %token GITEA_ERR_PREF GITEA_ERR_SUFF
+/* pfSense GUI authentication failures */
+%token PFSENSE_AUTH_FAIL
 
 %%
 
@@ -183,6 +185,7 @@ msg_single:
   | couriermsg        { attack->service = SERVICES_COURIER; }
   | openvpnmsg        { attack->service = SERVICES_OPENVPN; }
   | giteamsg          { attack->service = SERVICES_GITEA; }
+  | pfsenseauthfail   { attack->service = SERVICES_PFSENSE; }
   ;
 
 /* an address */
@@ -335,6 +338,11 @@ clfcmsmsg:
 opensmtpdmsg:
     OPENSMTPD_FAILED_CMD_PREF addr OPENSMTPD_AUTHFAIL_SUFF
   | OPENSMTPD_FAILED_CMD_PREF addr OPENSMTPD_UNSUPPORTED_CMD_SUFF
+  ;
+
+/* attack rules against pfSense */
+pfsenseauthfail:
+    PFSENSE_AUTH_FAIL addr
   ;
 
 /* attack rules for courier imap/pop */
