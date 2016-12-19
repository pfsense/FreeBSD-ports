*** dhcp6c_ia.c	Wed Mar 21 09:52:55 2007
--- dhcp6c_ia.c.edit	Mon Nov 28 17:54:18 2016
***************
*** 89,94 ****
--- 89,95 ----
  
  static char *iastr __P((iatype_t));
  static char *statestr __P((iastate_t));
+ extern int opt_norelease;
  
  void
  update_ia(iatype, ialist, ifp, serverid, authparam)
***************
*** 420,426 ****
  		for (ia = TAILQ_FIRST(&iac->iadata); ia; ia = ia_next) {
  			ia_next = TAILQ_NEXT(ia, link);
  
! 			(void)release_ia(ia);
  
  			/*
  			 * The client MUST stop using all of the addresses
--- 421,433 ----
  		for (ia = TAILQ_FIRST(&iac->iadata); ia; ia = ia_next) {
  			ia_next = TAILQ_NEXT(ia, link);
  
! 			if(opt_norelease != 1)
! 			{
! 			    dprintf(LOG_INFO,FNAME,"Start address release");
! 			    (void)release_ia(ia);
! 			} else {
! 			    dprintf(LOG_INFO,FNAME,"Bypassing address release");
! 			}
  
  			/*
  			 * The client MUST stop using all of the addresses
