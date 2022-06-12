--- chrome/browser/notifications/notification_display_service_impl.cc.orig	2022-05-11 07:16:48 UTC
+++ chrome/browser/notifications/notification_display_service_impl.cc
@@ -31,7 +31,7 @@
 #endif
 
 #if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC) || \
-    defined(OS_WIN) || defined(OS_FUCHSIA)
+    defined(OS_WIN) || defined(OS_FUCHSIA) || defined(OS_BSD)
 #include "chrome/browser/send_tab_to_self/desktop_notification_handler.h"
 #include "chrome/browser/sharing/sharing_notification_handler.h"
 #endif
@@ -64,7 +64,7 @@ NotificationDisplayServiceImpl* NotificationDisplaySer
 // static
 void NotificationDisplayServiceImpl::RegisterProfilePrefs(
     user_prefs::PrefRegistrySyncable* registry) {
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   registry->RegisterBooleanPref(prefs::kAllowNativeNotifications, true);
   registry->RegisterBooleanPref(prefs::kAllowSystemNotifications, true);
 #endif
@@ -81,7 +81,7 @@ NotificationDisplayServiceImpl::NotificationDisplaySer
                            std::make_unique<PersistentNotificationHandler>());
 
 #if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC) || \
-    defined(OS_WIN)
+    defined(OS_WIN) || defined(OS_BSD)
     AddNotificationHandler(
         NotificationHandler::Type::SEND_TAB_TO_SELF,
         std::make_unique<send_tab_to_self::DesktopNotificationHandler>(
@@ -89,7 +89,7 @@ NotificationDisplayServiceImpl::NotificationDisplaySer
 #endif
 
 #if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_MAC) || \
-    defined(OS_WIN)
+    defined(OS_WIN) || defined(OS_BSD)
     AddNotificationHandler(
         NotificationHandler::Type::TAILORED_SECURITY,
         std::make_unique<safe_browsing::TailoredSecurityNotificationHandler>());
