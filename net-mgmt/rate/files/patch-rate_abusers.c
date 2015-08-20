--- rate_abusers.c.orig	2013-03-26 10:14:00.000000000 +0000
+++ rate_abusers.c	2013-03-26 10:15:53.000000000 +0000
@@ -195,8 +195,7 @@
 	struct ip *iph;
 	u_int32_t sip;
 	u_int32_t dip;
-	u_int32_t host;
-	long long in = 0, out = 0;
+	int slocal = 0, dlocal = 0;
 
 	if(caplen < sizeof(struct ip)) return;
 
@@ -206,14 +205,21 @@
 	sip = iph->ip_src.s_addr;
 	dip = iph->ip_dst.s_addr;
 
- 	if(is_ours(ipci, sip)) out = len;
-	if(is_ours(ipci, dip)) in = len;
+	if (!len)
+		return;
 
-	if(!(in || out)) return;
-	if(in && out && (!opt_local)) return;
-
-	if(in)  add_entry(ntohl(dip), in, 0);
-	if(out) add_entry(ntohl(sip), 0, out);
+	slocal = is_ours(ipci, sip);
+	dlocal = is_ours(ipci, dip);
+	if(slocal && dlocal && (!opt_local)) return;
+
+	if(slocal && !dlocal)
+		add_entry(ntohl(sip), 0, (long long)len);
+	else if(!slocal && dlocal)
+		add_entry(ntohl(dip), (long long)len, 0);
+	else if(slocal && dlocal) {
+		add_entry(ntohl(dip), (long long)len, 0);
+		add_entry(ntohl(sip), 0, (long long)len);
+	}
 }
 
 void r_abusers_setup(int argc, char ** argv,
