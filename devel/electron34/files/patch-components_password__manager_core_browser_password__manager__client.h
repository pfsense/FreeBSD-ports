--- components/password_manager/core/browser/password_manager_client.h.orig	2025-01-27 17:37:37 UTC
+++ components/password_manager/core/browser/password_manager_client.h
@@ -30,7 +30,7 @@
 #include "net/cert/cert_status_flags.h"
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "base/i18n/rtl.h"
 #include "components/password_manager/core/browser/password_cross_domain_confirmation_popup_controller.h"
 #include "ui/gfx/geometry/rect_f.h"
@@ -551,7 +551,7 @@ class PasswordManagerClient {
       CredentialsCallback callback);
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_MAC) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 
   // Shows the bubble with the details of the `form`.
   virtual void OpenPasswordDetailsBubble(
