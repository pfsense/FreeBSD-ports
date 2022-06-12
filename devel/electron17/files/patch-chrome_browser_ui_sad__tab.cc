--- chrome/browser/ui/sad_tab.cc.orig	2022-05-11 07:16:49 UTC
+++ chrome/browser/ui/sad_tab.cc
@@ -188,7 +188,7 @@ std::vector<int> SadTab::GetSubMessages() {
       // Only show Incognito suggestion if not already in Incognito mode.
       if (!web_contents_->GetBrowserContext()->IsOffTheRecord())
         message_ids.insert(message_ids.begin(), IDS_SAD_TAB_RELOAD_INCOGNITO);
-#if defined(OS_MAC) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_MAC) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
       // Note: on macOS, Linux and ChromeOS, the first bullet is either one of
       // IDS_SAD_TAB_RELOAD_CLOSE_TABS or IDS_SAD_TAB_RELOAD_CLOSE_NOTABS
       // followed by one of the above suggestions.
