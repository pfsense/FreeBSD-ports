--- chrome/browser/notifications/notification_platform_bridge_delegator.cc.orig	2022-10-01 07:40:07 UTC
+++ chrome/browser/notifications/notification_platform_bridge_delegator.cc
@@ -57,7 +57,7 @@ bool SystemNotificationsEnabled(Profile* profile) {
 #elif BUILDFLAG(IS_WIN)
   return NotificationPlatformBridgeWin::SystemNotificationEnabled();
 #else
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   if (profile) {
     // Prefs take precedence over flags.
     PrefService* prefs = profile->GetPrefs();
