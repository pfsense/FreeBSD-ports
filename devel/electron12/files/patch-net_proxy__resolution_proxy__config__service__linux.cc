--- net/proxy_resolution/proxy_config_service_linux.cc.orig	2021-01-07 00:36:38 UTC
+++ net/proxy_resolution/proxy_config_service_linux.cc
@@ -6,7 +6,9 @@
 
 #include <errno.h>
 #include <limits.h>
+#if !defined(OS_BSD)
 #include <sys/inotify.h>
+#endif
 #include <unistd.h>
 
 #include <map>
@@ -511,6 +513,7 @@ int StringToIntOrDefault(base::StringPiece value, int 
   return default_value;
 }
 
+#if !defined(OS_BSD)
 // This is the KDE version that reads kioslaverc and simulates gsettings.
 // Doing this allows the main Delegate code, as well as the unit tests
 // for it, to stay the same - and the settings map fairly well besides.
@@ -1001,6 +1004,7 @@ class SettingGetterImplKDE : public ProxyConfigService
 
   DISALLOW_COPY_AND_ASSIGN(SettingGetterImplKDE);
 };
+#endif
 
 }  // namespace
 
@@ -1215,8 +1219,10 @@ ProxyConfigServiceLinux::Delegate::Delegate(
     case base::nix::DESKTOP_ENVIRONMENT_KDE3:
     case base::nix::DESKTOP_ENVIRONMENT_KDE4:
     case base::nix::DESKTOP_ENVIRONMENT_KDE5:
+#if !defined(OS_BSD)
       setting_getter_.reset(new SettingGetterImplKDE(env_var_getter_.get()));
       break;
+#endif
     case base::nix::DESKTOP_ENVIRONMENT_XFCE:
     case base::nix::DESKTOP_ENVIRONMENT_OTHER:
       break;
