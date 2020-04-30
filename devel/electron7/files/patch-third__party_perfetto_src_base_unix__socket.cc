--- third_party/perfetto/src/base/unix_socket.cc.orig	2019-12-12 12:45:21 UTC
+++ third_party/perfetto/src/base/unix_socket.cc
@@ -523,7 +523,8 @@ void UnixSocket::DoConnect(const std::string& socket_n
 
 void UnixSocket::ReadPeerCredentials() {
 #if PERFETTO_BUILDFLAG(PERFETTO_OS_LINUX) || \
-    PERFETTO_BUILDFLAG(PERFETTO_OS_ANDROID)
+    PERFETTO_BUILDFLAG(PERFETTO_OS_ANDROID) || \
+    PERFETTO_BUILDFLAG(PERFETTO_OS_FREEBSD)
   struct ucred user_cred;
   socklen_t len = sizeof(user_cred);
   int fd = sock_raw_.fd();
