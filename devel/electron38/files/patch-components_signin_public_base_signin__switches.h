--- components/signin/public/base/signin_switches.h.orig	2025-08-26 20:49:50 UTC
+++ components/signin/public/base/signin_switches.h
@@ -61,7 +61,7 @@ BASE_DECLARE_FEATURE(kSmartEmailLineBreaking);
 BASE_DECLARE_FEATURE(kSmartEmailLineBreaking);
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 COMPONENT_EXPORT(SIGNIN_SWITCHES)
 BASE_DECLARE_FEATURE(kEnableHistorySyncOptin);
 COMPONENT_EXPORT(SIGNIN_SWITCHES)
@@ -222,7 +222,7 @@ bool IsExtensionsExplicitBrowserSigninEnabled();
 COMPONENT_EXPORT(SIGNIN_SWITCHES)
 bool IsExtensionsExplicitBrowserSigninEnabled();
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // Uses the Material Next theme for the signin promo.
 COMPONENT_EXPORT(SIGNIN_SWITCHES)
 BASE_DECLARE_FEATURE(kSignInPromoMaterialNextUI);
