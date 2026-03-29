--- sendmail/sendmail.h.orig	2025-10-23 19:33:31 UTC
+++ sendmail/sendmail.h
@@ -73,6 +73,10 @@ SM_UNUSED(static char SmailId[]) = "@(#)$Id: sendmail.
 #endif
 
 #include "bf.h"
+#if USE_BLOCKLIST
+#include <blocklist.h>
+#endif
+#include "blocklist_client.h"
 #include "timers.h"
 #include <sm/exc.h>
 #include <sm/gen.h>
@@ -2807,6 +2811,10 @@ EXTERN int ConnectionRateWindowSize;
 #endif
 
 EXTERN int ConnectionRateWindowSize;
+
+#if USE_BLOCKLIST
+EXTERN bool	UseBlocklist;
+#endif
 
 /*
 **  Declarations of useful functions
