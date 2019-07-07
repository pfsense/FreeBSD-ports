--- src/parser/attack_parser.y.orig	2018-12-29 19:38:19 UTC
+++ src/parser/attack_parser.y
@@ -108,6 +108,8 @@ static void yyerror(attack_t *, const char *);
 %token COURIER_AUTHFAIL_PREF
 /* OpenVPN */
 %token OPENVPN_TLS_ERR_SUFF
+/* pfSense GUI authentication failures */
+%token PFSENSE_AUTH_FAIL
 
 %%
 
@@ -189,6 +191,7 @@ msg_single:
     | opensmtpdmsg      {   attack->service = SERVICES_OPENSMTPD; }
     | couriermsg        {   attack->service = SERVICES_COURIER; }
     | openvpnmsg        {   attack->service = SERVICES_OPENVPN; }
+    | pfsenseauthfail   {   attack->service = SERVICES_PFSENSE; }
     ;
 
 /* an address */
@@ -346,6 +349,11 @@ clfwordpressmsg:
 opensmtpdmsg:
     OPENSMTPD_FAILED_CMD_PREF addr OPENSMTPD_AUTHFAIL_SUFF
     | OPENSMTPD_FAILED_CMD_PREF addr OPENSMTPD_UNSUPPORTED_CMD_SUFF
+    ;
+
+/* attack rules against pfSense */
+pfsenseauthfail:
+    PFSENSE_AUTH_FAIL addr
     ;
 
 /* attack rules for courier imap/pop */
