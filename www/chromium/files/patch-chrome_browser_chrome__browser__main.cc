--- chrome/browser/chrome_browser_main.cc.orig	2020-03-16 18:40:29 UTC
+++ chrome/browser/chrome_browser_main.cc
@@ -224,9 +224,9 @@
 #include "components/arc/metrics/stability_metrics_manager.h"
 #endif  // defined(OS_CHROMEOS)
 
-#if defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#if (defined(OS_BSD) || defined(OS_LINUX)) && !defined(OS_CHROMEOS)
 #include "chrome/browser/first_run/upgrade_util_linux.h"
-#endif  // defined(OS_LINUX) && !defined(OS_CHROMEOS)
+#endif  // (defined(OS_BSD) || defined(OS_LINUX)) && !defined(OS_CHROMEOS)
 
 #if defined(OS_LINUX)
 #include "components/crash/content/app/breakpad_linux.h"
@@ -265,7 +265,7 @@
 #endif  // defined(OS_WIN)
 
 #if defined(OS_WIN) || defined(OS_MACOSX) || \
-    (defined(OS_LINUX) && !defined(OS_CHROMEOS))
+    (defined(OS_LINUX) && !defined(OS_CHROMEOS) || defined(OS_BSD))
 #include "chrome/browser/metrics/desktop_session_duration/desktop_session_duration_tracker.h"
 #include "chrome/browser/profiles/profile_activity_metrics_recorder.h"
 #endif
@@ -1068,7 +1068,7 @@ int ChromeBrowserMainParts::PreCreateThreadsImpl() {
       AddFirstRunNewTabs(browser_creator_.get(), master_prefs_->new_tabs);
     }
 
-#if defined(OS_MACOSX) || defined(OS_LINUX)
+#if defined(OS_MACOSX) || defined(OS_LINUX) || defined(OS_BSD)
     // Create directory for user-level Native Messaging manifest files. This
     // makes it less likely that the directory will be created by third-party
     // software with incorrect owner or permission. See crbug.com/725513 .
@@ -1077,7 +1077,7 @@ int ChromeBrowserMainParts::PreCreateThreadsImpl() {
                                  &user_native_messaging_dir));
     if (!base::PathExists(user_native_messaging_dir))
       base::CreateDirectory(user_native_messaging_dir);
-#endif  // defined(OS_MACOSX) || defined(OS_LINUX)
+#endif  // defined(OS_MACOSX) || defined(OS_LINUX) || defined(OS_BSD)
   }
 #endif  // !defined(OS_ANDROID) && !defined(OS_CHROMEOS)
 
@@ -1116,7 +1116,7 @@ int ChromeBrowserMainParts::PreCreateThreadsImpl() {
   }
 
 #if defined(OS_WIN) || defined(OS_MACOSX) || \
-    (defined(OS_LINUX) && !defined(OS_CHROMEOS))
+    (defined(OS_LINUX) && !defined(OS_CHROMEOS) || defined(OS_BSD))
   metrics::DesktopSessionDurationTracker::Initialize();
   ProfileActivityMetricsRecorder::Initialize();
 #endif
@@ -1269,6 +1269,7 @@ void ChromeBrowserMainParts::PostBrowserStart() {
       base::TimeDelta::FromMinutes(1));
 
 #if !defined(OS_ANDROID)
+#if !defined(OS_BSD)
   if (base::FeatureList::IsEnabled(features::kWebUsb)) {
     web_usb_detector_.reset(new WebUsbDetector());
     base::PostTask(
@@ -1277,6 +1278,7 @@ void ChromeBrowserMainParts::PostBrowserStart() {
         base::BindOnce(&WebUsbDetector::Initialize,
                        base::Unretained(web_usb_detector_.get())));
   }
+#endif
   if (base::FeatureList::IsEnabled(features::kTabMetricsLogging)) {
     // Initialize the TabActivityWatcher to begin logging tab activity events.
     resource_coordinator::TabActivityWatcher::GetInstance();
