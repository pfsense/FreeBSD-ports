--- components/password_manager/core/browser/ui/passwords_grouper.cc.orig	2023-09-05 21:57:56 UTC
+++ components/password_manager/core/browser/ui/passwords_grouper.cc
@@ -403,7 +403,11 @@ absl::optional<PasskeyCredential> PasswordsGrouper::Ge
   const std::vector<PasskeyCredential>& passkeys =
       map_group_id_to_credentials_[group_id_iterator->second].passkeys;
   const auto passkey_it =
+#if (_LIBCPP_VERSION >= 160000)
       std::ranges::find_if(passkeys, [&credential](const auto& passkey) {
+#else
+      base::ranges::find_if(passkeys, [&credential](const auto& passkey) {
+#endif
         return credential.passkey_credential_id == passkey.credential_id();
       });
   if (passkey_it == passkeys.end()) {
