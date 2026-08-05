--- src/pattern.c.orig	2026-03-19 15:52:20 UTC
+++ src/pattern.c
@@ -391,8 +391,14 @@ int pat_parse_dotted_ver(const char *tex
  */
 int pat_parse_ip(const char *text, struct pattern *pattern, int mflags, char **err)
 {
-	if (str2net(text, !(mflags & PAT_MF_NO_DNS) && (global.mode & MODE_STARTING),
-	            &pattern->val.ipv4.addr, &pattern->val.ipv4.mask)) {
+	/* Try literal parsing of both address families first, and only then
+	 * fall back to a DNS resolution attempt for the IPv4 case. Resolving
+	 * before trying the IPv6 literal parsing would cost a useless blocking
+	 * DNS round trip for every IPv6 entry of a pattern file, since an IPv6
+	 * literal can never be a valid host name (colons are not permitted in
+	 * DNS labels).
+	 */
+	if (str2net(text, 0, &pattern->val.ipv4.addr, &pattern->val.ipv4.mask)) {
 		pattern->type = SMP_T_IPV4;
 		return 1;
 	}
@@ -400,6 +406,11 @@ int pat_parse_ip(const char *text, struc
 		pattern->type = SMP_T_IPV6;
 		return 1;
 	}
+	else if (!(mflags & PAT_MF_NO_DNS) && (global.mode & MODE_STARTING) &&
+	         str2net(text, 1, &pattern->val.ipv4.addr, &pattern->val.ipv4.mask)) {
+		pattern->type = SMP_T_IPV4;
+		return 1;
+	}
 	else {
 		memprintf(err, "'%s' is not a valid IPv4 or IPv6 address", text);
 		return 0;
