--- chrome/browser/webauthn/chrome_authenticator_request_delegate.cc.orig	2023-03-30 00:33:45 UTC
+++ chrome/browser/webauthn/chrome_authenticator_request_delegate.cc
@@ -587,7 +587,7 @@ void ChromeAuthenticatorRequestDelegate::ConfigureCabl
     g_observer->ConfiguringCable(request_type);
   }
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // No caBLEv1 on Linux. It tends to crash bluez.
   if (base::Contains(pairings_from_extension,
                      device::CableDiscoveryData::Version::V1,
