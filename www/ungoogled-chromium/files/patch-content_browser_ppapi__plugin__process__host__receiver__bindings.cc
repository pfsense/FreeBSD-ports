--- content/browser/ppapi_plugin_process_host_receiver_bindings.cc.orig	2022-10-01 07:40:07 UTC
+++ content/browser/ppapi_plugin_process_host_receiver_bindings.cc
@@ -8,7 +8,7 @@
 
 #include "build/build_config.h"
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "components/services/font/public/mojom/font_service.mojom.h"  // nogncheck
 #include "content/browser/font_service.h"  // nogncheck
 #endif
@@ -17,7 +17,7 @@ namespace content {
 
 void PpapiPluginProcessHost::BindHostReceiver(
     mojo::GenericPendingReceiver receiver) {
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   if (auto font_receiver = receiver.As<font_service::mojom::FontService>())
     ConnectToFontService(std::move(font_receiver));
 #endif
