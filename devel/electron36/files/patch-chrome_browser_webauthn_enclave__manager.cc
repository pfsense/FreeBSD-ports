--- chrome/browser/webauthn/enclave_manager.cc.orig	2025-04-22 20:15:27 UTC
+++ chrome/browser/webauthn/enclave_manager.cc
@@ -689,7 +689,7 @@ std::string UserVerifyingLabelToString(crypto::UserVer
 
 std::string UserVerifyingLabelToString(crypto::UserVerifyingKeyLabel label) {
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   return label;
 #else
   return std::string("placeholder");
@@ -699,7 +699,7 @@ std::optional<crypto::UserVerifyingKeyLabel> UserVerif
 std::optional<crypto::UserVerifyingKeyLabel> UserVerifyingKeyLabelFromString(
     std::string saved_label) {
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   return saved_label;
 #else
   return std::nullopt;
