--- content/utility/utility_blink_platform_with_sandbox_support_impl.h.orig	2022-05-11 07:16:51 UTC
+++ content/utility/utility_blink_platform_with_sandbox_support_impl.h
@@ -10,7 +10,7 @@
 #include "build/build_config.h"
 #include "third_party/blink/public/platform/platform.h"
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 #include "components/services/font/public/cpp/font_loader.h"  // nogncheck
 #include "third_party/skia/include/core/SkRefCnt.h"           // nogncheck
 #endif
@@ -38,10 +38,10 @@ class UtilityBlinkPlatformWithSandboxSupportImpl : pub
   blink::WebSandboxSupport* GetSandboxSupport() override;
 
  private:
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC) || defined(OS_BSD)
   std::unique_ptr<blink::WebSandboxSupport> sandbox_support_;
 #endif
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   sk_sp<font_service::FontLoader> font_loader_;
 #endif
 };
