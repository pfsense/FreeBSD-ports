*** common.h	Mon Nov 28 16:04:22 2016
--- common.h.edit	Mon Nov 28 17:54:38 2016
***************
*** 179,185 ****
  extern int ifaddrconf __P((ifaddrconf_cmd_t, char *, struct sockaddr_in6 *,
  			   int, int, int));
  extern int safefile __P((const char *));
! 
  /* missing */
  #ifndef HAVE_STRLCAT
  extern size_t strlcat __P((char *, const char *, size_t));
--- 179,185 ----
  extern int ifaddrconf __P((ifaddrconf_cmd_t, char *, struct sockaddr_in6 *,
  			   int, int, int));
  extern int safefile __P((const char *));
! extern int opt_norelease;
  /* missing */
  #ifndef HAVE_STRLCAT
  extern size_t strlcat __P((char *, const char *, size_t));
