--- content/renderer/renderer_blink_platform_impl.cc.orig	2019-09-16 09:24:24 UTC
+++ content/renderer/renderer_blink_platform_impl.cc
@@ -108,7 +108,7 @@
 
 #if defined(OS_MACOSX)
 #include "content/child/child_process_sandbox_support_impl_mac.h"
-#elif defined(OS_LINUX)
+#elif defined(OS_LINUX) || defined(OS_BSD)
 #include "content/child/child_process_sandbox_support_impl_linux.h"
 #endif
 
@@ -199,7 +199,7 @@ RendererBlinkPlatformImpl::RendererBlinkPlatformImpl(
                      ->Clone();
     thread_safe_sender_ = RenderThreadImpl::current()->thread_safe_sender();
     blob_registry_.reset(new WebBlobRegistryImpl(thread_safe_sender_.get()));
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
     font_loader_ = sk_make_sp<font_service::FontLoader>(connector_.get());
     SkFontConfigInterface::SetGlobal(font_loader_);
 #endif
@@ -208,7 +208,7 @@ RendererBlinkPlatformImpl::RendererBlinkPlatformImpl(
     connector_ = service_manager::Connector::Create(&request);
   }
 
-#if defined(OS_LINUX) || defined(OS_MACOSX)
+#if defined(OS_LINUX) || defined(OS_MACOSX) || defined(OS_BSD)
   if (g_sandbox_enabled && sandboxEnabled()) {
 #if defined(OS_MACOSX)
     sandbox_support_.reset(new WebSandboxSupportMac(connector_.get()));
@@ -236,7 +236,7 @@ RendererBlinkPlatformImpl::~RendererBlinkPlatformImpl(
 }
 
 void RendererBlinkPlatformImpl::Shutdown() {
-#if defined(OS_LINUX) || defined(OS_MACOSX)
+#if defined(OS_LINUX) || defined(OS_MACOSX) || defined(OS_BSD)
   // SandboxSupport contains a map of OutOfProcessFont objects, which hold
   // WebStrings and WebVectors, which become invalidated when blink is shut
   // down. Hence, we need to clear that map now, just before blink::shutdown()
@@ -311,7 +311,7 @@ RendererBlinkPlatformImpl::CreateNetworkURLLoaderFacto
 
 void RendererBlinkPlatformImpl::SetDisplayThreadPriority(
     base::PlatformThreadId thread_id) {
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   if (RenderThreadImpl* render_thread = RenderThreadImpl::current()) {
     render_thread->render_message_filter()->SetThreadPriority(
         thread_id, base::ThreadPriority::DISPLAY);
@@ -324,7 +324,7 @@ blink::BlameContext* RendererBlinkPlatformImpl::GetTop
 }
 
 blink::WebSandboxSupport* RendererBlinkPlatformImpl::GetSandboxSupport() {
-#if defined(OS_LINUX) || defined(OS_MACOSX)
+#if defined(OS_LINUX) || defined(OS_MACOSX) || defined(OS_BSD)
   return sandbox_support_.get();
 #else
   // These platforms do not require sandbox support.
