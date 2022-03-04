--- src/3rdparty/chromium/services/network/network_service.h.orig	2019-05-23 12:39:34 UTC
+++ src/3rdparty/chromium/services/network/network_service.h
@@ -186,7 +186,7 @@ class COMPONENT_EXPORT(NETWORK_SERVICE) NetworkService
 #endif  // !BUILDFLAG(IS_CT_SUPPORTED)
   void UpdateCRLSet(base::span<const uint8_t> crl_set) override;
   void OnCertDBChanged() override;
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_LINUX) && !defined(OS_CHROMEOS)) || defined(OS_BSD)
   void SetCryptConfig(mojom::CryptConfigPtr crypt_config) override;
 #endif
 #if defined(OS_MACOSX) && !defined(OS_IOS)
