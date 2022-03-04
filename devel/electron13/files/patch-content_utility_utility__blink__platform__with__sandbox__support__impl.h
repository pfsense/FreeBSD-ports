--- content/utility/utility_blink_platform_with_sandbox_support_impl.h.orig	2021-01-07 00:36:34 UTC
+++ content/utility/utility_blink_platform_with_sandbox_support_impl.h
@@ -11,7 +11,7 @@
 #include "build/build_config.h"
 #include "third_party/blink/public/platform/platform.h"
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 #include "components/services/font/public/cpp/font_loader.h"  // nogncheck
 #include "third_party/skia/include/core/SkRefCnt.h"           // nogncheck
 #endif
@@ -33,10 +33,10 @@ class UtilityBlinkPlatformWithSandboxSupportImpl : pub
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
 
