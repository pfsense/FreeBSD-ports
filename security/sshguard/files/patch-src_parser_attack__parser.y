--- src/parser/attack_parser.y.orig	2023-06-27 18:24:17 UTC
+++ src/parser/attack_parser.y
@@ -113,6 +113,8 @@ static void yyerror(attack_t *, const char *);
 %token OPENVPN_TLS_ERR_SUFF
 /* Gitea */
 %token GITEA_ERR_PREF GITEA_ERR_SUFF
+/* pfSense GUI authentication failures */
+%token PFSENSE_AUTH_FAIL
 /* OpenVPN Portshare */
 %token OPENVPN_PS_TERM_PREF
 %token OPENVPN_PS_TERM_SUFF
@@ -192,6 +194,7 @@ msg_single:
   | couriermsg        { attack->service = SERVICES_COURIER; }
   | openvpnmsg        { attack->service = SERVICES_OPENVPN; }
   | giteamsg          { attack->service = SERVICES_GITEA; }
+  | pfsenseauthfail   { attack->service = SERVICES_PFSENSE; }
   | openvpnpsmsg      { attack->service = SERVICES_OPENVPN_PS; }
   | sqlservrmsg       { attack->service = SERVICES_MSSQL; }
   ;
@@ -351,6 +354,11 @@ opensmtpdmsg:
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
