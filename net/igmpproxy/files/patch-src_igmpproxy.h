--- src/igmpproxy.h.orig	2016-02-28 22:07:18 UTC
+++ src/igmpproxy.h
@@ -172,7 +172,7 @@ extern int upStreamVif;
 /* ifvc.c
  */
 void buildIfVc( void );
-struct IfDesc *getIfByName( const char *IfName );
+struct IfDesc *getIfByName( const char *IfName, int iponly );
 struct IfDesc *getIfByIx( unsigned Ix );
 struct IfDesc *getIfByAddress( uint32_t Ix );
 int isAdressValidForIf(struct IfDesc* intrface, uint32_t ipaddr);
