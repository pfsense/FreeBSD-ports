--- components/flags_ui/flags_state.cc.orig	2023-07-24 14:27:53 UTC
+++ components/flags_ui/flags_state.cc
@@ -673,7 +673,7 @@ unsigned short FlagsState::GetCurrentPlatform() {
   return kOsCrOS;
 #elif BUILDFLAG(IS_CHROMEOS_LACROS)
   return kOsLacros;
-#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_OPENBSD)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return kOsLinux;
 #elif BUILDFLAG(IS_ANDROID)
   return kOsAndroid;
