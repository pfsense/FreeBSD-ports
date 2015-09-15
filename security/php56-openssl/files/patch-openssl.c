--- openssl.c.orig	2015-09-03 00:02:45 UTC
+++ openssl.c
@@ -17,6 +17,7 @@
    |          Sascha Kettler <kettler@gmx.net>                            |
    |          Pierre-Alain Joye <pierre@php.net>                          |
    |          Marc Delling <delling@silpion.de> (PKCS12 functions)        |		
+   |          Moritz Bechler <mbechler@eenterphace.org> (CRL support)     |		
    +----------------------------------------------------------------------+
  */
 
@@ -51,6 +52,7 @@
 #include <openssl/rand.h>
 #include <openssl/ssl.h>
 #include <openssl/pkcs12.h>
+#include <openssl/ocsp.h>
 
 /* Common */
 #include <time.h>
@@ -122,6 +124,39 @@ PHP_FUNCTION(openssl_dh_compute_key);
 PHP_FUNCTION(openssl_random_pseudo_bytes);
 
 /* {{{ arginfo */
+ZEND_BEGIN_ARG_INFO_EX(arginfo_openssl_crl_export, 0, 0, 3)
+    ZEND_ARG_INFO(0, crl)
+    ZEND_ARG_INFO(1, data)
+    ZEND_ARG_INFO(0, capkey)
+    ZEND_ARG_INFO(0, crlv2)
+    ZEND_ARG_INFO(0, notext)
+    ZEND_ARG_INFO(0, capass)
+ZEND_END_ARG_INFO()
+
+ZEND_BEGIN_ARG_INFO_EX(arginfo_openssl_crl_revoke_cert, 0, 0, 3)
+    ZEND_ARG_INFO(0, crl)
+    ZEND_ARG_INFO(0, certficate)
+    ZEND_ARG_INFO(0, revokation_date)
+    ZEND_ARG_INFO(0, reason)
+    ZEND_ARG_INFO(0, compromise_date)
+    ZEND_ARG_INFO(0, hold_instruction)
+ZEND_END_ARG_INFO()
+
+ZEND_BEGIN_ARG_INFO_EX(arginfo_openssl_crl_revoke_cert_by_serial, 0, 0, 3)
+    ZEND_ARG_INFO(0, crl)
+    ZEND_ARG_INFO(0, revoke_serial)
+    ZEND_ARG_INFO(0, revokation_date)
+    ZEND_ARG_INFO(0, reason)
+    ZEND_ARG_INFO(0, compromise_date)
+    ZEND_ARG_INFO(0, hold_instruction)
+ZEND_END_ARG_INFO()
+
+ZEND_BEGIN_ARG_INFO_EX(arginfo_openssl_crl_new, 0, 0, 1)
+    ZEND_ARG_INFO(0, cacert)
+    ZEND_ARG_INFO(0, crlserial)
+    ZEND_ARG_INFO(0, lifetime)
+ZEND_END_ARG_INFO()
+
 ZEND_BEGIN_ARG_INFO_EX(arginfo_openssl_x509_export_to_file, 0, 0, 2)
     ZEND_ARG_INFO(0, x509)
     ZEND_ARG_INFO(0, outfilename)
@@ -467,6 +502,12 @@ const zend_function_entry openssl_functi
 	PHP_FE(openssl_pkcs12_export_to_file,	arginfo_openssl_pkcs12_export_to_file)
 	PHP_FE(openssl_pkcs12_read,				arginfo_openssl_pkcs12_read)
 
+/* for CRL creation */
+	PHP_FE(openssl_crl_new,			arginfo_openssl_crl_new)
+	PHP_FE(openssl_crl_revoke_cert_by_serial,       arginfo_openssl_crl_revoke_cert_by_serial)
+	PHP_FE(openssl_crl_revoke_cert,         arginfo_openssl_crl_revoke_cert)
+	PHP_FE(openssl_crl_export,              arginfo_openssl_crl_export)
+
 /* CSR funcs */
 	PHP_FE(openssl_csr_new,				arginfo_openssl_csr_new)
 	PHP_FE(openssl_csr_export,			arginfo_openssl_csr_export)
@@ -533,6 +574,7 @@ ZEND_GET_MODULE(openssl)
 static int le_key;
 static int le_x509;
 static int le_csr;
+static int le_crl;
 static int ssl_stream_data_index;
 
 int php_openssl_get_x509_list_id(void) /* {{{ */
@@ -541,6 +583,16 @@ int php_openssl_get_x509_list_id(void) /
 }
 /* }}} */
 
+/* {{{ */
+struct php_x509_crl {
+	X509_CRL *crl;
+	X509 *cacert;
+	long lifetime;
+	int forcev2;
+};
+/* }}} */
+
+
 /* {{{ resource destructors */
 static void php_pkey_free(zend_rsrc_list_entry *rsrc TSRMLS_DC)
 {
@@ -562,6 +614,21 @@ static void php_csr_free(zend_rsrc_list_
 	X509_REQ * csr = (X509_REQ*)rsrc->ptr;
 	X509_REQ_free(csr);
 }
+
+static void php_crl_free(zend_rsrc_list_entry *rsrc TSRMLS_DC)
+{
+	struct php_x509_crl *res = (struct php_x509_crl*)rsrc->ptr;
+
+	if(res) {
+		if(res->crl != NULL) {
+			X509_CRL_free(res->crl);
+		}
+
+		efree(res);
+	}
+
+}
+
 /* }}} */
 
 /* {{{ openssl open_basedir check */
@@ -1122,6 +1189,7 @@ PHP_MINIT_FUNCTION(openssl)
 	le_key = zend_register_list_destructors_ex(php_pkey_free, NULL, "OpenSSL key", module_number);
 	le_x509 = zend_register_list_destructors_ex(php_x509_free, NULL, "OpenSSL X.509", module_number);
 	le_csr = zend_register_list_destructors_ex(php_csr_free, NULL, "OpenSSL X.509 CSR", module_number);
+	le_crl = zend_register_list_destructors_ex(php_crl_free, NULL, "OpenSSL X.509 CRL", module_number);
 
 	SSL_library_init();
 	OpenSSL_add_all_ciphers();
@@ -1212,6 +1280,36 @@ PHP_MINIT_FUNCTION(openssl)
 	REGISTER_LONG_CONSTANT("OPENSSL_RAW_DATA", OPENSSL_RAW_DATA, CONST_CS|CONST_PERSISTENT);
 	REGISTER_LONG_CONSTANT("OPENSSL_ZERO_PADDING", OPENSSL_ZERO_PADDING, CONST_CS|CONST_PERSISTENT);
 
+        /* OCSP revokation states */
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_NOSTATUS", OCSP_REVOKED_STATUS_NOSTATUS, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_UNSPECIFIED", OCSP_REVOKED_STATUS_UNSPECIFIED, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_KEYCOMPROMISE", OCSP_REVOKED_STATUS_KEYCOMPROMISE, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_CACOMPROMISE", OCSP_REVOKED_STATUS_CACOMPROMISE, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_AFFILIATIONCHANGED", OCSP_REVOKED_STATUS_AFFILIATIONCHANGED, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_SUPERSEDED", OCSP_REVOKED_STATUS_SUPERSEDED, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_CESSATIONOFOPERATION", OCSP_REVOKED_STATUS_CESSATIONOFOPERATION, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_CERTIFICATEHOLD", OCSP_REVOKED_STATUS_CERTIFICATEHOLD, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OCSP_REVOKED_STATUS_REMOVEFROMCRL", OCSP_REVOKED_STATUS_REMOVEFROMCRL, CONST_CS | CONST_PERSISTENT);
+
+        /* X509 Hold instruction NIDs */
+        REGISTER_LONG_CONSTANT("OPENSSL_HOLDINSTRUCTION_NONE", NID_hold_instruction_none, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OPENSSL_HOLDINSTRUCTION_CALL_ISSUER", NID_hold_instruction_call_issuer, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("OPENSSL_HOLDINSTRUCTION_REJECT", NID_hold_instruction_reject, CONST_CS | CONST_PERSISTENT);
+
+        /* openssl_verify flags */
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_CB_ISSUER_CHECK", X509_V_FLAG_CB_ISSUER_CHECK, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_USE_CHECK_TIME", X509_V_FLAG_USE_CHECK_TIME, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_CRL_CHECK", X509_V_FLAG_CRL_CHECK, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_CRL_CHECK_ALL", X509_V_FLAG_CRL_CHECK_ALL, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_IGNORE_CRITICAL", X509_V_FLAG_IGNORE_CRITICAL, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_X509_STRICT", X509_V_FLAG_X509_STRICT, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_ALLOW_PROXY_CERTS", X509_V_FLAG_ALLOW_PROXY_CERTS, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_POLICY_CHECK", X509_V_FLAG_POLICY_CHECK, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_EXPLICIT_POLICY", X509_V_FLAG_EXPLICIT_POLICY, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_INHIBIT_ANY", X509_V_FLAG_INHIBIT_ANY, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_INHIBIT_MAP", X509_V_FLAG_INHIBIT_MAP, CONST_CS | CONST_PERSISTENT);
+        REGISTER_LONG_CONSTANT("X509_V_FLAG_NOTIFY_POLICY", X509_V_FLAG_NOTIFY_POLICY, CONST_CS | CONST_PERSISTENT);
+
 #if OPENSSL_VERSION_NUMBER >= 0x0090806fL && !defined(OPENSSL_NO_TLSEXT)
 	/* SNI support included in OpenSSL >= 0.9.8j */
 	REGISTER_LONG_CONSTANT("OPENSSL_TLSEXT_SERVER_NAME", 1, CONST_CS|CONST_PERSISTENT);
@@ -5028,7 +5126,423 @@ PHP_FUNCTION(openssl_open)
 }
 /* }}} */
 
+/* CRL creation functions */
+
+/* {{{ proto resource openssl_crl_new(mixed cacert[, int crlserial[, string int lifetime]])
+   Creates a new CRL */
+PHP_FUNCTION(openssl_crl_new)
+{
+	struct php_x509_crl *res = NULL;
+	long serial = 0;
+	ASN1_INTEGER *crl_number;
+	long lifetime_days = 80;
+	
+	long certresource;
+	zval* zcacert;
+	
+	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z|ll", &zcacert, &serial, &lifetime_days) == FAILURE) {
+		RETURN_NULL();
+	}
+
+	res = emalloc(sizeof(struct php_x509_crl));
+	memset(res, 0, sizeof(struct php_x509_crl));
+	res->lifetime = 60*60*24*lifetime_days;
+
+	/* Initialize CRL */
+	res->crl = X509_CRL_new();
+	if(res->crl == NULL) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING,
+						"Failed to create CRL");
+		efree(res);
+		RETURN_NULL();
+	}
+
+	res->cacert = php_openssl_x509_from_zval(&zcacert, 0, &certresource TSRMLS_CC);
+
+	if(!res->cacert) {
+		X509_CRL_free(res->crl);
+		efree(res);
+
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to load CA Certificate");
+		RETURN_NULL();
+	}
+
+	if (!X509_CRL_set_issuer_name(res->crl, X509_get_subject_name(res->cacert))) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Invalid ca certificate");
+		X509_free(res->cacert);
+		X509_CRL_free(res->crl);
+		efree(res);
+		RETURN_NULL();
+	}
+
+	/* set CRL number (serial) (forces the crl to be version 2) */
+	if(serial) {
+		crl_number = ASN1_INTEGER_new();
+		ASN1_INTEGER_set(crl_number, serial);
+
+		if(!X509_CRL_add1_ext_i2d(res->crl,NID_crl_number,crl_number,0,0)) {
+			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to set CRL serial");
+			ASN1_INTEGER_free(crl_number);
+			X509_free(res->cacert);
+			X509_CRL_free(res->crl);
+			efree(res);
+			RETURN_NULL();
+		}
+		res->forcev2 = 1;
+
+		ASN1_INTEGER_free(crl_number);
+	}
+	
+	ZEND_REGISTER_RESOURCE(return_value, res, le_crl);
+}
+/* }}} */
+
+/* {{{ Adds an entry to the CRL */
+static int php_crl_revoke_serial(struct php_x509_crl *res, ASN1_INTEGER *serial, time_t rev_timestamp, long reason_code, time_t comp_timestamp, int hold TSRMLS_DC)
+{
+	X509_REVOKED *revoke;
+
+	ASN1_TIME *rev_date;
+	ASN1_GENERALIZEDTIME *comp_date;
+
+	ASN1_ENUMERATED *rtmp;
+	
+	ASN1_OBJECT *hold_instruction;
+	
+	if(!res) {
+		return FAILURE;
+	}
+	
+	revoke = X509_REVOKED_new();
+
+	if(!revoke) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to revoke cert");
+		X509_REVOKED_free(revoke);
+		return FAILURE;
+	}
+
+	/* Add serial to the CRL */	
+	if(!X509_REVOKED_set_serialNumber(revoke, serial)) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to set serial number");
+		X509_REVOKED_free(revoke);
+		return FAILURE;
+	}
+
+	/* Revokation date */
+	rev_date = ASN1_UTCTIME_new();
+	if (!ASN1_UTCTIME_set(rev_date, rev_timestamp) || !X509_REVOKED_set_revocationDate(revoke,rev_date)) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to set revokation time");
+		ASN1_UTCTIME_free(rev_date);
+		X509_REVOKED_free(revoke);
+		return FAILURE;
+	}
+	ASN1_UTCTIME_free(rev_date);
+
+
+	if(reason_code && reason_code != OCSP_REVOKED_STATUS_UNSPECIFIED) {
+		rtmp = ASN1_ENUMERATED_new();
+		ASN1_ENUMERATED_set(rtmp, reason_code);
+		
+		if(!X509_REVOKED_add1_ext_i2d(revoke, NID_crl_reason, rtmp, 0, 0)) {
+			ASN1_ENUMERATED_free(rtmp);
+			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to set revokation reason");
+			return FAILURE;
+		}
+		res->forcev2 = 1;
+		ASN1_ENUMERATED_free(rtmp);
+	}
+
+	/* Add compromise timestamp if reason is key or ca compromise */
+	if(comp_timestamp && (reason_code == OCSP_REVOKED_STATUS_KEYCOMPROMISE || reason_code == OCSP_REVOKED_STATUS_CACOMPROMISE)) {
+		comp_date = ASN1_GENERALIZEDTIME_new();
+		if (!ASN1_GENERALIZEDTIME_set(comp_date, comp_timestamp) ||
+				!X509_REVOKED_add1_ext_i2d(revoke, NID_invalidity_date, comp_date, 0, 0)) {
+			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to set compromise time");
+			ASN1_GENERALIZEDTIME_free(comp_date);
+			return FAILURE;
+		}
+		ASN1_GENERALIZEDTIME_free(comp_date);
+	}
+	
+	/* Add hold instruction if reason is certificateHold */
+	if(reason_code == OCSP_REVOKED_STATUS_CERTIFICATEHOLD) {
+		hold_instruction = OBJ_nid2obj(hold);
+		
+		if (!X509_REVOKED_add1_ext_i2d(revoke, NID_hold_instruction_code, hold_instruction, 0, 0)) {
+			ASN1_OBJECT_free(hold_instruction);
+			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to set hold instruction");
+			return FAILURE;
+		}
+		
+		
+		ASN1_OBJECT_free(hold_instruction);
+	}
+
+	X509_CRL_add0_revoked(res->crl,revoke);
+	
+	return SUCCESS;
+}
+/* }}} */
+
+/* {{{ proto bool openssl_crl_revoke_cert_by_serial(resource crl, string revoke_serial, int revokation_date[, int reason[, int compromise_date[, int hold_instruction]]])
+   Adds a certificate to the revokation list using its serial */
+PHP_FUNCTION(openssl_crl_revoke_cert_by_serial) 
+{
+
+	time_t rev_timestamp;
+	time_t comp_timestamp;
+
+	zval *crl_res;
+	struct php_x509_crl *res = NULL;
 
+	long reason_code = OCSP_REVOKED_STATUS_UNSPECIFIED;
+	zval *serial_num;
+	
+	long hold = NID_hold_instruction_reject;
+	
+	ASN1_INTEGER *serial;
+
+	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rzl|lll", 
+			&crl_res, &serial_num, &rev_timestamp, &reason_code, &comp_timestamp, &hold) == FAILURE) {
+		RETURN_FALSE;
+	}
+
+
+	ZEND_FETCH_RESOURCE(res, struct php_x509_crl*, &crl_res, -1, "OpenSSL X.509 CRL", le_crl);
+		
+	if(!res) {
+		RETURN_FALSE;
+	}
+
+	
+	convert_to_string(serial_num);
+	
+	serial = ASN1_INTEGER_new();
+	serial = s2i_ASN1_INTEGER(NULL, Z_STRVAL_P(serial_num));
+	
+	if(php_crl_revoke_serial(res, serial, rev_timestamp, reason_code, comp_timestamp, hold TSRMLS_CC) != SUCCESS) {
+		ASN1_INTEGER_free(serial);
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to revoke certificate");
+		RETURN_FALSE;
+	}	
+	ASN1_INTEGER_free(serial);
+
+	RETURN_TRUE;
+}
+
+/* {{{ proto bool openssl_revoke_cert(resource crl, mixed certficate, int revokation_date[, int reason[, int compromise_date[, int hold_instruction]]])
+   Adds a certificate to the revokation list using a certificate resource */
+PHP_FUNCTION(openssl_crl_revoke_cert) 
+{
+	time_t rev_timestamp;
+	time_t comp_timestamp;
+
+	zval *crl_res;
+	struct php_x509_crl *res = NULL;
+
+	long reason_code = OCSP_REVOKED_STATUS_UNSPECIFIED;
+	
+	X509 *cert;
+	zval *zcert;
+	long certresource;
+	
+	long hold = NID_hold_instruction_reject;
+	
+	ASN1_INTEGER *serial;
+
+	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rzl|lll", 
+			&crl_res, &zcert, &rev_timestamp, &reason_code, &comp_timestamp, &hold) == FAILURE) {
+		RETURN_FALSE;
+	}
+
+
+	ZEND_FETCH_RESOURCE(res, struct php_x509_crl*, &crl_res, -1, "OpenSSL X.509 CRL", le_crl);
+		
+	if(!res) {
+		RETURN_FALSE;
+	}
+	
+	
+	cert = php_openssl_x509_from_zval(&zcert, 0, &certresource TSRMLS_CC);	
+	serial = X509_get_serialNumber(cert);
+	
+	if(php_crl_revoke_serial(res, serial, rev_timestamp, reason_code, comp_timestamp, hold TSRMLS_CC) != SUCCESS) {
+		ASN1_INTEGER_free(serial);
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to revoke certificate");
+		RETURN_FALSE;
+	}	
+	ASN1_INTEGER_free(serial);
+
+	RETURN_TRUE;
+}
+
+/* Generates the CRL */
+static int php_crl_generate(struct php_x509_crl *res, zval* zcapkey, int crlv2, char* capass, long capass_len, long digest_alg TSRMLS_DC) 
+{
+	long capkeyres;
+	EVP_PKEY *capkey;
+	EVP_MD *digest;
+	ASN1_TIME *tmptm;
+	CONF *conf = NULL;
+	long errorline = -1;
+	char *crl_ext = NULL, *section = NULL;
+
+	/* Read crl_extensions from config if present */
+	conf = NCONF_new(NULL);
+	if (NCONF_load(conf, default_ssl_conf_filename, &errorline) <= 0) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Error loading OpenSSL config file");
+		return FAILURE;
+	}
+
+	section = NCONF_get_string(conf, "ca", "default_ca");
+	if (section == NULL)
+		strlcpy(section, "CA_default", 10);
+	crl_ext = NCONF_get_string(conf, "CA_default", "crl_extensions");
+
+	if (crl_ext) {
+		X509V3_CTX ctx;
+		X509V3_set_ctx_test(&ctx);
+		X509V3_set_nconf(&ctx, conf);
+		if (!X509V3_EXT_add_nconf(conf, &ctx, crl_ext, NULL)) {
+			php_error_docref(NULL  TSRMLS_CC, E_WARNING, "Error loading CRL extension section");
+			NCONF_free(conf);
+			return FAILURE;
+		}
+
+		X509V3_CTX crlctx;
+		X509V3_set_ctx(&crlctx, res->cacert, NULL, NULL, res->crl, 0);
+		X509V3_set_nconf(&crlctx, conf);
+
+		if (!X509V3_EXT_CRL_add_nconf(conf, &crlctx, crl_ext, res->crl)) {
+			NCONF_free(conf);
+			return FAILURE;
+		}
+
+		NCONF_free(conf);
+	}
+	
+	/* Prepare last updated and next update timestamps */
+	tmptm = ASN1_TIME_new();
+	if (!tmptm) {
+		return FAILURE;
+	}
+	X509_gmtime_adj(tmptm,0);
+	X509_CRL_set_lastUpdate(res->crl, tmptm);	
+	X509_gmtime_adj(tmptm,res->lifetime);
+	X509_CRL_set_nextUpdate(res->crl, tmptm);	
+	ASN1_TIME_free(tmptm);
+
+	/* Initialize message digest */
+	
+	
+	digest = php_openssl_get_evp_md_from_algo(digest_alg);
+	if (digest == NULL)
+	{
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Invalid message digest");
+		return FAILURE;
+	}
+
+	/* Sorts the CRL */
+	X509_CRL_sort(res->crl);
+
+	/* Check whether to use version 2 crls */
+	if(crlv2 || res->forcev2) {
+		if (!X509_CRL_set_version(res->crl, 1)) {
+			php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to use v2 CRLs");
+			return FAILURE;
+		}
+	}
+	
+	
+	if(!crlv2 && res->forcev2) {
+			php_error_docref(NULL TSRMLS_CC, E_NOTICE, "The CRL contains extensions which need a V2 CRL, creating a V2 CRL");
+	}
+	
+	capkey = php_openssl_evp_from_zval(&zcapkey, 0, capass, 0, &capkeyres TSRMLS_CC);
+
+	if(!capkey) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to read CA private key");
+		return FAILURE;
+	}
+
+	/* verify private key */
+	if (!X509_check_private_key(res->cacert,capkey)) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "CA Cert does not match private key");
+		return FAILURE;
+	}
+
+	/* sign CRL */
+	if (!X509_CRL_sign(res->crl,capkey,digest)) {
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to sign CRL");
+		return FAILURE;
+	}
+	
+	return SUCCESS;
+}
+
+/* {{{ proto bool openssl_crl_export(resource crl, string &data, mixed capkey[, bool crlv2[, bool notext[, string capass]]])
+   Exports a CRL to a string */
+PHP_FUNCTION(openssl_crl_export) 
+{
+	BIO *out;
+	BUF_MEM *buf;
+	
+	zval *crl_res;
+	struct php_x509_crl *res = NULL;
+		
+	zval *zcapkey;
+	
+	char *capass;
+	long capass_len;
+
+	int crlv2 = 1;
+	int notext = 1;
+	
+	long digest_alg = OPENSSL_ALGO_SHA1;
+	
+	zval *output;
+	
+	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "rzz|bbsl",
+							&crl_res, &output,		
+							&zcapkey,
+							&crlv2, &notext,
+							&capass, &capass_len,
+							&digest_alg) == FAILURE) {
+		RETURN_NULL();
+	}
+
+	ZEND_FETCH_RESOURCE(res, struct php_x509_crl*, &crl_res, -1, "OpenSSL X.509 CRL", le_crl);
+		
+	if(!res) {
+		RETURN_FALSE;
+	}
+	
+	if(php_crl_generate(res, zcapkey, crlv2, capass, capass_len, digest_alg TSRMLS_CC) != SUCCESS) {
+		RETURN_FALSE;
+	}
+
+	
+	out = BIO_new(BIO_s_mem());
+	if (!notext) {
+		X509_CRL_print(out, res->crl);
+	}
+	
+	if (!PEM_write_bio_X509_CRL(out, res->crl))  {
+		BIO_free(out);
+		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to export CRL");
+		RETURN_FALSE;
+	}
+
+	zval_dtor(output);
+	BIO_get_mem_ptr(out, &buf);
+	ZVAL_STRINGL(output, buf->data, buf->length, 1);
+	
+	BIO_free(out);
+	
+	RETURN_TRUE;
+	
+}
+/* }}} */
 
 static void openssl_add_method_or_alias(const OBJ_NAME *name, void *arg) /* {{{ */
 {
