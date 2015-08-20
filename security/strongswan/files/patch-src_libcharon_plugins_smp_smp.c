--- src/libcharon/plugins/smp/smp.c.orig	2013-11-01 11:40:35.000000000 +0100
+++ src/libcharon/plugins/smp/smp.c	2014-09-11 17:37:30.000000000 +0200
@@ -24,6 +24,8 @@
 #include <unistd.h>
 #include <errno.h>
 #include <signal.h>
+#include <inttypes.h>
+#include <time.h>
 #include <libxml/xmlreader.h>
 #include <libxml/xmlwriter.h>
 
@@ -176,15 +178,98 @@
  */
 static void write_child(xmlTextWriterPtr writer, child_sa_t *child)
 {
+	time_t use_in, use_out, rekey, now;
+	u_int64_t bytes_in, bytes_out, packets_in, packets_out;
 	child_cfg_t *config;
+	proposal_t *proposal;
 
 	config = child->get_config(child);
+	now = time_monotonic(NULL);
 
 	xmlTextWriterStartElement(writer, "childsa");
 	xmlTextWriterWriteFormatElement(writer, "reqid", "%d",
 									child->get_reqid(child));
 	xmlTextWriterWriteFormatElement(writer, "childconfig", "%s",
 									config->get_name(config));
+
+	xmlTextWriterWriteFormatElement(writer, "state", "%N", child_sa_state_names, child->get_state(child));
+	xmlTextWriterWriteFormatElement(writer, "mode", "%N%s", ipsec_mode_names, child->get_mode(child),
+				config->use_proxy_mode(config) ? "_PROXY" : "");
+
+	if (child->get_state(child) == CHILD_INSTALLED || child->get_state(child) == CHILD_REKEYING)
+	{
+		xmlTextWriterWriteFormatElement(writer, "protocol", "%N", protocol_id_names, child->get_protocol(child));
+		xmlTextWriterWriteFormatElement(writer, "encap", "%s", child->has_encap(child) ? "yes" : "no");
+
+		if (child->get_ipcomp(child) != IPCOMP_NONE)
+		{
+			xmlTextWriterWriteFormatElement(writer, "ipcomp", "%.4x_i %.4x_o",
+				ntohs(child->get_cpi(child, TRUE)), ntohs(child->get_cpi(child, FALSE)));
+		} else
+			xmlTextWriterWriteElement(writer, "ipcomp", "none");
+
+		proposal = child->get_proposal(child);
+		if (proposal)
+		{
+			u_int16_t alg, ks;
+
+			if (proposal->get_algorithm(proposal, ENCRYPTION_ALGORITHM, &alg, &ks))
+			{
+				xmlTextWriterWriteFormatElement(writer, "encalg", "%N:%u", encryption_algorithm_names, alg, ks ? ks : 0);
+			}
+			if (proposal->get_algorithm(proposal, INTEGRITY_ALGORITHM, &alg, &ks))
+			{
+				xmlTextWriterWriteFormatElement(writer, "intalg", "%N:%u", integrity_algorithm_names, alg, ks ? ks : 0);
+			}
+			if (proposal->get_algorithm(proposal, PSEUDO_RANDOM_FUNCTION, &alg, NULL))
+			{
+				xmlTextWriterWriteFormatElement(writer, "prfalg", "%N", pseudo_random_function_names, alg);
+			}
+			if (proposal->get_algorithm(proposal, DIFFIE_HELLMAN_GROUP, &alg, NULL))
+			{
+				xmlTextWriterWriteFormatElement(writer, "dhgroup", "%N", diffie_hellman_group_names, alg);
+			}
+			proposal->get_algorithm(proposal, EXTENDED_SEQUENCE_NUMBERS, &alg, NULL);
+			xmlTextWriterWriteElement(writer, "esn", alg == EXT_SEQ_NUMBERS ? "ESN" : "");
+		}
+
+		child->get_usestats(child, TRUE,
+							   &use_in, &bytes_in, &packets_in);
+		xmlTextWriterWriteFormatElement(writer, "bytesin", "%" PRIu64, bytes_in);
+		xmlTextWriterWriteFormatElement(writer, "packetsin", "%" PRIu64 " : %" PRIu64,
+			use_in ? packets_in : 0, use_in ? (u_int64_t)(now - use_in) : 0);
+
+		child->get_usestats(child, FALSE,
+							   &use_out, &bytes_out, &packets_out);
+		xmlTextWriterWriteFormatElement(writer, "bytesout", "%" PRIu64, bytes_out);
+		xmlTextWriterWriteFormatElement(writer, "packetsout", "%" PRIu64 " : %" PRIu64,
+			use_out ? packets_out : 0, use_out ? (u_int64_t)(now - use_out) : 0);
+
+		rekey = child->get_lifetime(child, FALSE);
+		if (rekey)
+		{
+			if (now > rekey)
+			{
+				xmlTextWriterWriteElement(writer, "rekey", "active");
+			}
+			else
+			{
+				xmlTextWriterWriteFormatElement(writer, "rekey", "%V", &now, &rekey);
+			}
+		}
+		else
+		{
+			xmlTextWriterWriteElement(writer, "rekey", "disabled");
+		}
+		rekey = child->get_lifetime(child, TRUE);
+		if (rekey)
+		{
+			xmlTextWriterWriteFormatElement(writer, "lifetime", "%V", &now, &rekey);
+		}
+		rekey = child->get_installtime(child);
+		xmlTextWriterWriteFormatElement(writer, "installtime", "%V", &now, &rekey);
+	}
+
 	xmlTextWriterStartElement(writer, "local");
 	write_childend(writer, child, TRUE);
 	xmlTextWriterEndElement(writer);
@@ -199,9 +284,12 @@
  */
 static void request_query_ikesa(xmlTextReaderPtr reader, xmlTextWriterPtr writer)
 {
+	time_t now;
 	enumerator_t *enumerator;
 	ike_sa_t *ike_sa;
 
+	now = time_monotonic(NULL);
+
 	/* <ikesalist> */
 	xmlTextWriterStartElement(writer, "ikesalist");
 
@@ -213,6 +301,8 @@
 		host_t *local, *remote;
 		enumerator_t *children;
 		child_sa_t *child_sa;
+		identification_t *eap_id;
+		proposal_t *proposal;
 
 		id = ike_sa->get_id(ike_sa);
 
@@ -224,6 +314,15 @@
 		xmlTextWriterWriteElement(writer, "role",
 							id->is_initiator(id) ? "initiator" : "responder");
 		xmlTextWriterWriteElement(writer, "peerconfig", ike_sa->get_name(ike_sa));
+		xmlTextWriterWriteFormatElement(writer, "version", "%u", ike_sa->get_version(ike_sa));
+
+		if (ike_sa->get_state(ike_sa) == IKE_ESTABLISHED)
+		{
+			time_t established;
+
+			established = ike_sa->get_statistic(ike_sa, STAT_ESTABLISHED);
+			xmlTextWriterWriteFormatElement(writer, "established", "%V ago", &now, &established);
+		}
 
 		/* <local> */
 		local = ike_sa->get_my_host(ike_sa);
@@ -256,9 +355,79 @@
 		{
 			write_bool(writer, "nat", ike_sa->has_condition(ike_sa, COND_NAT_THERE));
 		}
+
+		xmlTextWriterStartElement(writer, "auth");
+		eap_id = ike_sa->get_other_eap_id(ike_sa);
+		if (!eap_id->equals(eap_id, ike_sa->get_other_id(ike_sa)))
+		{
+			xmlTextWriterWriteFormatElement(writer, "identity", "%s: %Y",
+				ike_sa->get_version(ike_sa) == IKEV1 ? "XAuth" : "EAP",
+				eap_id);
+		}
+
+		xmlTextWriterEndElement(writer);
+
 		xmlTextWriterEndElement(writer);
 		/* </remote> */
 
+		if (ike_sa->get_state(ike_sa) == IKE_ESTABLISHED)
+		{
+			time_t rekey, reauth;
+
+			rekey = ike_sa->get_statistic(ike_sa, STAT_REKEY);
+
+			if (rekey)
+			{
+				xmlTextWriterWriteFormatElement(writer, "rekey", "%V", &rekey, &now);
+			}
+			reauth = ike_sa->get_statistic(ike_sa, STAT_REAUTH);
+			if (reauth)
+			{
+#if 0
+				auth_cfg_t *auth;
+				peer_cfg_t *peer_cfg;
+				enumerator_t *enumerator;
+
+				peer_cfg = ike_sa->get_peer_cfg(ike_sa);
+
+				enumerator = peer_cfg->create_auth_cfg_enumerator(peer_cfg, TRUE);
+				while (enumerator->enumerate(enumerator, &auth))
+				{
+					xmlTextWriterWriteFormatElement(writer, "reauthclass", "%N", auth_class_names,
+						auth->get(auth, AUTH_RULE_AUTH_CLASS));
+				}
+				enumerator->destroy(enumerator);
+#endif
+				xmlTextWriterWriteFormatElement(writer, "reauth", "%V", &reauth, &now);
+			}
+			if (!rekey && !reauth)
+			{
+				xmlTextWriterWriteElement(writer, "rekey", "disabled");
+			}
+		}
+		proposal = ike_sa->get_proposal(ike_sa);
+		if (proposal)
+		{
+			u_int16_t alg, ks;
+
+			if (proposal->get_algorithm(proposal, ENCRYPTION_ALGORITHM, &alg, &ks))
+			{
+				xmlTextWriterWriteFormatElement(writer, "encalg", "%N:%u", encryption_algorithm_names, alg, ks ? ks : 0);
+			}
+			if (proposal->get_algorithm(proposal, INTEGRITY_ALGORITHM, &alg, &ks))
+			{
+				xmlTextWriterWriteFormatElement(writer, "intalg", "%N:%u", integrity_algorithm_names, alg, ks ? ks : 0);
+			}
+			if (proposal->get_algorithm(proposal, PSEUDO_RANDOM_FUNCTION, &alg, NULL))
+			{
+				xmlTextWriterWriteFormatElement(writer, "prfalg", "%N", pseudo_random_function_names, alg);
+			}
+			if (proposal->get_algorithm(proposal, DIFFIE_HELLMAN_GROUP, &alg, NULL))
+			{
+				xmlTextWriterWriteFormatElement(writer, "dhgroup", "%N", diffie_hellman_group_names, alg);
+			}
+		}
+
 		/* <childsalist> */
 		xmlTextWriterStartElement(writer, "childsalist");
 		children = ike_sa->create_child_sa_enumerator(ike_sa);
@@ -737,7 +906,7 @@ METHOD(plugin_t, destroy, void,
  */
 plugin_t *smp_plugin_create()
 {
-	struct sockaddr_un unix_addr = { AF_UNIX, IPSEC_PIDDIR "/charon.xml"};
+	struct sockaddr_un unix_addr;
 	private_smp_t *this;
 	mode_t old;
 
@@ -766,6 +935,11 @@ plugin_t *smp_plugin_create()
 		return NULL;
 	}
 
+	strlcpy(unix_addr.sun_path, IPSEC_PIDDIR "/charon.xml",
+	    sizeof(unix_addr.sun_path));
+	unix_addr.sun_len = sizeof(unix_addr);
+	unix_addr.sun_family = PF_LOCAL;
+
 	unlink(unix_addr.sun_path);
 	old = umask(S_IRWXO);
 	if (bind(this->socket, (struct sockaddr *)&unix_addr, sizeof(unix_addr)) < 0)
