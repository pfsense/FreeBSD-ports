--- net/dns/host_resolver_proc.cc.orig	2025-05-28 14:55:43 UTC
+++ net/dns/host_resolver_proc.cc
@@ -16,10 +16,6 @@
 #include "net/base/net_errors.h"
 #include "net/dns/host_resolver_system_task.h"
 
-#if BUILDFLAG(IS_OPENBSD)
-#define AI_ADDRCONFIG 0
-#endif
-
 namespace net {
 
 HostResolverProc* HostResolverProc::default_proc_ = nullptr;
