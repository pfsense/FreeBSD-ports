--- components/device_signals/core/browser/user_permission_service_impl.cc.orig	2023-07-24 14:27:53 UTC
+++ components/device_signals/core/browser/user_permission_service_impl.cc
@@ -74,7 +74,7 @@ bool UserPermissionServiceImpl::ShouldCollectConsent()
          consent_required_by_dependent_policy;
 }
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 UserPermission UserPermissionServiceImpl::CanUserCollectSignals(
     const UserContext& user_context) const {
   // Return "unknown user" if no user ID was given.
