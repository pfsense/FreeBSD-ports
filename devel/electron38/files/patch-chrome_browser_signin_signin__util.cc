--- chrome/browser/signin/signin_util.cc.orig	2025-08-26 20:49:50 UTC
+++ chrome/browser/signin/signin_util.cc
@@ -90,7 +90,7 @@ void CookiesMover::StartMovingCookies() {
 CookiesMover::~CookiesMover() = default;
 
 void CookiesMover::StartMovingCookies() {
-#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
   bool allow_cookies_to_be_moved = base::FeatureList::IsEnabled(
       profile_management::features::kThirdPartyProfileManagement);
 #else
@@ -369,7 +369,7 @@ std::string SignedInStateToString(SignedInState state)
   }
 }
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
 bool ShouldShowHistorySyncOptinScreen(Profile& profile) {
   if (GetSignedInState(IdentityManagerFactory::GetForProfile(&profile)) !=
       signin_util::SignedInState::kSignedIn) {
