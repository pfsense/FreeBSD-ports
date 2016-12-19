--- dhcp6c_ia.c.orig	2007-03-21 09:52:55 UTC
+++ dhcp6c_ia.c
@@ -420,7 +420,12 @@ release_all_ia(ifp)
 		for (ia = TAILQ_FIRST(&iac->iadata); ia; ia = ia_next) {
 			ia_next = TAILQ_NEXT(ia, link);
 
-			(void)release_ia(ia);
+			if(opt_norelease != 1){
+			  dprintf(LOG_INFO, FNAME,"Start address release");
+			  (void)release_ia(ia);
+			} else {
+			  dprintf(LOG_NOTICE,FNAME,"Bypassing address release");
+			}
 
 			/*
 			 * The client MUST stop using all of the addresses
