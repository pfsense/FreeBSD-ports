--- src/libcharon/plugins/vici/vici_query.c.orig	2015-08-31 09:15:51.000000000 -0500
+++ src/libcharon/plugins/vici/vici_query.c	2015-10-29 05:20:00.000000000 -0500
@@ -239,9 +239,19 @@
 
 	b->add_kv(b, "local-host", "%H", ike_sa->get_my_host(ike_sa));
 	b->add_kv(b, "local-id", "%Y", ike_sa->get_my_id(ike_sa));
+
+	if (ike_sa->supports_extension(ike_sa, EXT_NATT) && ike_sa->has_condition(ike_sa, COND_NAT_HERE))
+	{
+		b->add_kv(b, "local-nat-t", "yes");
+	}
 
 	b->add_kv(b, "remote-host", "%H", ike_sa->get_other_host(ike_sa));
 	b->add_kv(b, "remote-id", "%Y", ike_sa->get_other_id(ike_sa));
+
+	if (ike_sa->supports_extension(ike_sa, EXT_NATT) && ike_sa->has_condition(ike_sa, COND_NAT_THERE))
+	{
+		b->add_kv(b, "remote-nat-t", "yes");
+	}
 
 	eap = ike_sa->get_other_eap_id(ike_sa);
 
