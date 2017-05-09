--- include/proto/dns.h.orig	2017-04-03 08:28:32 UTC
+++ include/proto/dns.h
@@ -30,7 +30,7 @@ int dns_str_to_dn_label_len(const char *
 int dns_hostname_validation(const char *string, char **err);
 int dns_build_query(int query_id, int query_type, char *hostname_dn, int hostname_dn_len, char *buf, int bufsize);
 struct task *dns_process_resolve(struct task *t);
-int dns_init_resolvers(int close_socket);
+int dns_init_resolvers(void);
 uint16_t dns_rnd16(void);
 int dns_validate_dns_response(unsigned char *resp, unsigned char *bufend, struct dns_response_packet *dns_p);
 int dns_get_ip_from_response(struct dns_response_packet *dns_p,
