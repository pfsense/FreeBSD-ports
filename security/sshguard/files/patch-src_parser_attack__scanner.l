--- src/parser/attack_scanner.l.orig	2018-07-05 17:04:47 UTC
+++ src/parser/attack_scanner.l
@@ -280,6 +280,8 @@ WORDPRESS_LOGIN            .*"/wp-login"(\.php)?
  /* Bad credentials */
 <opensmtpd_failedcmd>"host="{HOSTNAME}" command=\"AUTH ".+"\" result=\"535 Authentication failed\"" { BEGIN(INITIAL); return OPENSMTPD_AUTHFAIL_SUFF; }
 
+ /* pfSense */
+.+"webConfigurator authentication error for '"{WORD}"' from " { return PFSENSE_AUTH_FAIL; }
 
  /**         COMMON-USE TOKENS       do not touch these          **/
  /* an IPv4 address */
