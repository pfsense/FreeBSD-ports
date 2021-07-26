--- util/net_help.h.orig	2021-07-26 17:07:42 UTC
+++ util/net_help.h
@@ -42,6 +42,7 @@
 #ifndef NET_HELP_H
 #define NET_HELP_H
 #include "util/log.h"
+#include "util/random.h"
 struct sock_list;
 struct regional;
 struct config_strlist;
@@ -93,6 +94,9 @@ extern uint16_t EDNS_ADVERTISED_SIZE;
 #define DNSKEY_BIT_ZSK 0x0100
 /** DNSKEY secure entry point, KSK flag */
 #define DNSKEY_BIT_SEP 0x0001
+
+/** return a random 16-bit number given a random source */
+#define GET_RANDOM_ID(rnd) (((unsigned)ub_random(rnd)>>8) & 0xffff)
 
 /** minimal responses when positive answer */
 extern int MINIMAL_RESPONSES;
