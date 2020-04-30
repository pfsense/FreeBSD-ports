--- chrome/browser/ui/sad_tab.cc.orig	2019-12-12 12:39:17 UTC
+++ chrome/browser/ui/sad_tab.cc
@@ -181,7 +181,7 @@ std::vector<int> SadTab::GetSubMessages() {
       // Only show incognito suggestion if not already in Incognito mode.
       if (!web_contents_->GetBrowserContext()->IsOffTheRecord())
         message_ids.insert(message_ids.begin(), IDS_SAD_TAB_RELOAD_INCOGNITO);
-#if defined(OS_MACOSX) || defined(OS_LINUX)
+#if defined(OS_MACOSX) || defined(OS_LINUX) || defined(OS_BSD)
       // Note: on macOS, Linux and ChromeOS, the first bullet is either one of
       // IDS_SAD_TAB_RELOAD_CLOSE_TABS or IDS_SAD_TAB_RELOAD_CLOSE_NOTABS
       // followed by one of the above suggestions.
