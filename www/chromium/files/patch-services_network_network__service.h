--- services/network/network_service.h.orig	2020-03-16 18:40:34 UTC
+++ services/network/network_service.h
@@ -150,7 +150,7 @@ class COMPONENT_EXPORT(NETWORK_SERVICE) NetworkService
       base::span<const uint8_t> config,
       mojom::NetworkService::UpdateLegacyTLSConfigCallback callback) override;
   void OnCertDBChanged() override;
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   void SetCryptConfig(mojom::CryptConfigPtr crypt_config) override;
 #endif
 #if defined(OS_WIN) || (defined(OS_MACOSX) && !defined(OS_IOS))
