--- net/dns/host_resolver_proc.cc.orig	2021-01-07 00:36:38 UTC
+++ net/dns/host_resolver_proc.cc
@@ -159,7 +159,7 @@ int SystemHostResolverCall(const std::string& host,
   base::ScopedBlockingCall scoped_blocking_call(FROM_HERE,
                                                 base::BlockingType::WILL_BLOCK);
 
-#if defined(OS_POSIX) && !defined(OS_APPLE) && !defined(OS_OPENBSD) && \
+#if defined(OS_POSIX) && !defined(OS_APPLE) && !defined(OS_BSD) && \
     !defined(OS_ANDROID) && !defined(OS_FUCHSIA)
   DnsReloaderMaybeReload();
 #endif
