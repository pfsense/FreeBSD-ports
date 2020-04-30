--- chrome/renderer/chrome_content_renderer_client.cc.orig	2019-09-10 11:13:46 UTC
+++ chrome/renderer/chrome_content_renderer_client.cc
@@ -1011,7 +1011,7 @@ WebPlugin* ChromeContentRendererClient::CreatePlugin(
       }
 
       case chrome::mojom::PluginStatus::kRestartRequired: {
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
         placeholder = create_blocked_plugin(
             IDR_BLOCKED_PLUGIN_HTML,
             l10n_util::GetStringFUTF16(IDS_PLUGIN_RESTART_REQUIRED,
