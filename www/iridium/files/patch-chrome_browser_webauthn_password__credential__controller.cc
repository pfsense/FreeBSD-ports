--- chrome/browser/webauthn/password_credential_controller.cc.orig	2025-05-07 06:48:23 UTC
+++ chrome/browser/webauthn/password_credential_controller.cc
@@ -46,7 +46,7 @@ password_manager::PasswordManagerClient* GetPasswordMa
 }
 
 std::u16string GetAuthenticationMessage(std::string_view rp_id) {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return u"";
 #else
   return l10n_util::GetStringFUTF16(IDS_PASSWORD_MANAGER_FILLING_REAUTH,
