--- chrome/browser/extensions/api/passwords_private/passwords_private_delegate_impl.cc.orig	2023-03-30 00:33:42 UTC
+++ chrome/browser/extensions/api/passwords_private/passwords_private_delegate_impl.cc
@@ -523,7 +523,7 @@ void PasswordsPrivateDelegateImpl::OsReauthCall(
 }
 
 void PasswordsPrivateDelegateImpl::OsReauthTimeoutCall() {
-#if !BUILDFLAG(IS_LINUX)
+#if !BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_BSD)
   PasswordsPrivateEventRouter* router =
       PasswordsPrivateEventRouterFactory::GetForProfile(profile_);
   if (router)
