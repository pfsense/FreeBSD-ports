--- libpfctl.h.orig	2024-12-19 12:31:33 UTC
+++ libpfctl.h
@@ -174,7 +174,12 @@ struct pfctl_rule {
 	char			 overload_tblname[PF_TABLE_NAME_SIZE];
 
 	TAILQ_ENTRY(pfctl_rule)	 entries;
-	struct pfctl_pool	 rpool;
+	struct pfctl_pool	 nat;
+	union {
+		/* Alias old and new names. */
+		struct pfctl_pool	 rpool;
+		struct pfctl_pool	 rdr;
+	};
 
 	uint64_t		 evaluations;
 	uint64_t		 packets[2];
@@ -250,6 +255,7 @@ struct pfctl_rule {
 	uint8_t			 flush;
 	uint8_t			 prio;
 	uint8_t			 set_prio[2];
+	sa_family_t		 naf;
 
 	struct {
 		struct pf_addr		addr;
