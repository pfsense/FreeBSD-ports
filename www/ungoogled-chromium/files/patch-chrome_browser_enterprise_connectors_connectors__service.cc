--- chrome/browser/enterprise/connectors/connectors_service.cc.orig	2025-05-06 12:23:00 UTC
+++ chrome/browser/enterprise/connectors/connectors_service.cc
@@ -475,7 +475,7 @@ policy::PolicyScope ConnectorsService::GetPolicyScope(
 bool ConnectorsService::ConnectorsEnabled() const {
   Profile* profile = Profile::FromBrowserContext(context_);
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // On desktop, the guest profile is actually the primary OTR profile of
   // the "regular" guest profile.  The regular guest profile is never used
   // directly by users.  Also, user are not able to create child OTR profiles
