--- src/3rdparty/chromium/services/network/network_service.h.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/services/network/network_service.h
@@ -196,7 +196,7 @@ class COMPONENT_EXPORT(NETWORK_SERVICE) NetworkService
       base::span<const uint8_t> config,
       mojom::NetworkService::UpdateLegacyTLSConfigCallback callback) override;
   void OnCertDBChanged() override;
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   void SetCryptConfig(mojom::CryptConfigPtr crypt_config) override;
 #endif
 #if defined(OS_WIN) || defined(OS_MAC)
