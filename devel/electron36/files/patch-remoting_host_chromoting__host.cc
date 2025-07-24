--- remoting/host/chromoting_host.cc.orig	2025-04-22 20:15:27 UTC
+++ remoting/host/chromoting_host.cc
@@ -137,7 +137,7 @@ void ChromotingHost::Start(const std::string& host_own
       &ChromotingHost::OnIncomingSession, base::Unretained(this)));
 }
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 void ChromotingHost::StartChromotingHostServices() {
   DCHECK_CALLED_ON_VALID_SEQUENCE(sequence_checker_);
   DCHECK(!ipc_server_);
