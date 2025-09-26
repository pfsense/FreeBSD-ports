--- lease.c.orig	2017-02-28 19:06:15 UTC
+++ lease.c
@@ -47,6 +47,7 @@
 #include "dhcp6.h"
 #include "config.h"
 #include "common.h"
+#include "lease.h"
 
 #ifndef FALSE
 #define FALSE 	0
@@ -80,15 +81,15 @@ struct hash_table {
 
 static struct hash_table dhcp6_lease_table;
 
-static unsigned int in6_addr_hash __P((void *));
-static int in6_addr_match __P((void *, void *));
+static unsigned int in6_addr_hash(void *);
+static int in6_addr_match(void *, void *);
 
-static int hash_table_init __P((struct hash_table *, unsigned int,
-				pfn_hash_t, pfh_hash_match_t));
-static void hash_table_cleanup __P((struct hash_table *));
-static int hash_table_add __P((struct hash_table *, void *, unsigned int));
-static int hash_table_remove __P((struct hash_table *, void *));
-static struct hash_entry * hash_table_find __P((struct hash_table *, void *));
+static int hash_table_init(struct hash_table *, unsigned int,
+				pfn_hash_t, pfh_hash_match_t);
+static void hash_table_cleanup(struct hash_table *);
+static int hash_table_add(struct hash_table *, void *, unsigned int);
+static int hash_table_remove(struct hash_table *, void *);
+static struct hash_entry * hash_table_find(struct hash_table *, void *);
 
 int
 lease_init(void)
@@ -177,7 +178,7 @@ static unsigned int
 in6_addr_hash(val)
 	void *val;
 {
-	u_int8_t *addr = ((struct in6_addr *)val)->s6_addr;
+	uint8_t *addr = ((struct in6_addr *)val)->s6_addr;
 	unsigned int hash = 0;
 	int i;
 
@@ -208,7 +209,7 @@ hash_table_init(table, size, hash, match)
 	pfn_hash_t hash;
 	pfh_hash_match_t match;
 {
-	int i;
+	size_t i;
 
 	if (!table || !hash || !match) {
 		return (-1);
@@ -232,7 +233,7 @@ static void
 hash_table_cleanup(table)
 	struct hash_table *table; 
 {
-	int i;
+	size_t i;
 
 	if (!table) {
 		return;
