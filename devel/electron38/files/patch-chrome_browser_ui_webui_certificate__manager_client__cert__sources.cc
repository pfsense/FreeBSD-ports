--- chrome/browser/ui/webui/certificate_manager/client_cert_sources.cc.orig	2025-08-26 20:49:50 UTC
+++ chrome/browser/ui/webui/certificate_manager/client_cert_sources.cc
@@ -55,7 +55,7 @@
 #include "net/ssl/client_cert_store_mac.h"
 #endif  // BUILDFLAG(IS_MAC)
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 #include "chrome/browser/browser_process.h"
 #include "chrome/browser/enterprise/client_certificates/certificate_provisioning_service_factory.h"
 #include "chrome/browser/policy/chrome_browser_policy_connector.h"
@@ -130,7 +130,7 @@ class ClientCertStoreLoader {
       active_requests_;
 };
 
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 class ClientCertStoreFactoryNSS : public ClientCertStoreFactory {
  public:
   std::unique_ptr<net::ClientCertStore> CreateClientCertStore() override {
@@ -155,7 +155,7 @@ class ClientCertStoreFactoryMac : public ClientCertSto
 };
 #endif
 
-#if !BUILDFLAG(IS_CHROMEOS) && !BUILDFLAG(IS_LINUX)
+#if !BUILDFLAG(IS_CHROMEOS) && !BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_BSD)
 std::unique_ptr<ClientCertStoreLoader> CreatePlatformClientCertLoader(
     Profile* profile) {
 #if BUILDFLAG(IS_WIN)
@@ -170,7 +170,7 @@ std::unique_ptr<ClientCertStoreLoader> CreatePlatformC
 }
 #endif
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // ClientCertStore implementation that always returns an empty list. The
 // CertificateProvisioningService implementation expects to wrap a platform
 // cert store, but here we only want to get results from the provisioning
@@ -345,7 +345,7 @@ class ClientCertSource : public CertificateManagerPage
   std::optional<net::CertificateList> certs_;
 };
 
-#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // ChromeOS currently can use either Kcer or NSS for listing client certs, and
 // Linux uses NSS only. This interface provides an abstraction to hide that
 // from WritableClientCertSource. Currently this class only handles reading
@@ -1066,7 +1066,7 @@ CreatePlatformClientCertSource(
     mojo::Remote<certificate_manager::mojom::CertificateManagerPage>*
         remote_client,
     Profile* profile) {
-#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   return std::make_unique<WritableClientCertSource>(remote_client, profile);
 #else
   return std::make_unique<ClientCertSource>(
@@ -1074,7 +1074,7 @@ CreatePlatformClientCertSource(
 #endif
 }
 
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 std::unique_ptr<CertificateManagerPageHandler::CertSource>
 CreateProvisionedClientCertSource(Profile* profile) {
   return std::make_unique<ClientCertSource>(
@@ -1124,7 +1124,7 @@ bool ClientCertManagementAccessControls::IsChangeAllow
 
   return client_cert_policy_ == ClientCertificateManagementPermission::kAll;
 }
-#elif BUILDFLAG(IS_LINUX)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 ClientCertManagementAccessControls::ClientCertManagementAccessControls(
     Profile* profile) {}
 
