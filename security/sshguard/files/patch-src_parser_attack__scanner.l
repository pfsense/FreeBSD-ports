--- src/parser/attack_scanner.l.orig	2023-06-27 18:24:17 UTC
+++ src/parser/attack_scanner.l
@@ -257,7 +257,7 @@ HTTP_LOGIN_200OK_BAD       .*({WORDPRESS_LOGIN}|{TYPO3
 
  /* cyrus-imap login error */
 "badlogin: "[^\[]*"["                                           { BEGIN(cyrusimap_loginerr); return CYRUSIMAP_SASL_LOGINERR_PREF; }
-<cyrusimap_loginerr>"] ".*"SASL".*"failed".?$                   { BEGIN(INITIAL); return CYRUSIMAP_SASL_LOGINERR_SUFF; }
+<cyrusimap_loginerr>"] ".*"SASL".*"fail".*$                   { BEGIN(INITIAL); return CYRUSIMAP_SASL_LOGINERR_SUFF; }
 
  /* postfix */
 "warning: "({WORD}|{HOSTADDR})"["                               { BEGIN(postfix_loginerr); return POSTFIX_SASL_LOGINERR_PREF; }
@@ -319,6 +319,9 @@ HTTP_LOGIN_200OK_BAD       .*({WORDPRESS_LOGIN}|{TYPO3
 <opensmtpd_failedcmd>"host="{HOSTNAME}" command=\"".+"\" result=\"503 5.5.1 Invalid command: Command not supported\"" { BEGIN(INITIAL); return OPENSMTPD_UNSUPPORTED_CMD_SUFF; }
  /* Bad credentials */
 <opensmtpd_failedcmd>"host="{HOSTNAME}" command=\"AUTH ".+"\" result=\"535 Authentication failed\"" { BEGIN(INITIAL); return OPENSMTPD_AUTHFAIL_SUFF; }
+
+ /* pfSense */
+.+": webConfigurator authentication error for user '"{WORD}"' from: " { return PFSENSE_AUTH_FAIL; }
 
  /* Courier IMAP/POP */
 "LOGIN FAILED, "(user|method)=[^ ,]+", ip="                     { return COURIER_AUTHFAIL_PREF; }
