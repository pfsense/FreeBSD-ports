--- remoting/test/it2me_standalone_host.cc.orig	2021-01-07 00:36:39 UTC
+++ remoting/test/it2me_standalone_host.cc
@@ -49,7 +49,7 @@ It2MeStandaloneHost::It2MeStandaloneHost()
                context_->ui_task_runner()),
       connection_(base::WrapUnique(new testing::NiceMock<MockSession>())),
       session_jid_(kSessionJid),
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
       // We cannot support audio capturing for linux, since a pipe name is
       // needed to initialize AudioCapturerLinux.
       config_(protocol::SessionConfig::ForTest()),
