--- chrome/browser/ui/views/web_apps/web_app_integration_test_driver.cc.orig	2023-07-16 15:47:57 UTC
+++ chrome/browser/ui/views/web_apps/web_app_integration_test_driver.cc
@@ -406,7 +406,7 @@ std::string GetFileExtension(FileExtension file_extens
 }
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 SiteConfig GetSiteConfigurationFromAppName(const std::string& app_name) {
   SiteConfig config;
   bool is_app_found = false;
@@ -889,7 +889,7 @@ void WebAppIntegrationTestDriver::TearDownOnMainThread
   LOG(INFO) << "TearDownOnMainThread: Deleting dangling shortcuts.";
   // TODO(crbug.com/1273568): Investigate the true source of flakiness instead
   // of papering over it here.
-#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   ASSERT_TRUE(override_registration_->test_override->ForceDeleteAllShortcuts());
 #endif
   LOG(INFO)
@@ -1829,7 +1829,7 @@ void WebAppIntegrationTestDriver::DeletePlatformShortc
   if (app_name.empty()) {
     app_name = GetSiteConfiguration(site).app_name;
   }
-#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   ASSERT_TRUE(override_registration_->test_override->IsShortcutCreated(
       profile(), app_id, app_name));
   ASSERT_TRUE(
@@ -3009,7 +3009,7 @@ void WebAppIntegrationTestDriver::CheckRunOnOsLoginEna
           app_state->id, app_state->name);
   ASSERT_TRUE(icon_color.has_value());
   ASSERT_THAT(site_config.icon_color, testing::Eq(icon_color.value()));
-#elif BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#elif BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   ASSERT_TRUE(override_registration_->test_override->IsRunOnOsLoginEnabled(
       profile(), app_state->id, app_state->name));
 #endif
@@ -3024,7 +3024,7 @@ void WebAppIntegrationTestDriver::CheckRunOnOsLoginDis
       GetAppBySiteMode(after_state_change_action_state_.get(), profile(), site);
   ASSERT_TRUE(app_state);
   base::ScopedAllowBlockingForTesting allow_blocking;
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   ASSERT_FALSE(override_registration_->test_override->IsRunOnOsLoginEnabled(
       profile(), app_state->id, app_state->name));
 #endif
@@ -3034,7 +3034,7 @@ void WebAppIntegrationTestDriver::CheckRunOnOsLoginDis
 void WebAppIntegrationTestDriver::CheckSiteHandlesFile(
     Site site,
     FileExtension file_extension) {
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   if (!BeforeStateCheckAction(__FUNCTION__)) {
     return;
   }
@@ -3050,7 +3050,7 @@ void WebAppIntegrationTestDriver::CheckSiteHandlesFile
 void WebAppIntegrationTestDriver::CheckSiteNotHandlesFile(
     Site site,
     FileExtension file_extension) {
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   if (!BeforeStateCheckAction(__FUNCTION__)) {
     return;
   }
@@ -3799,7 +3799,7 @@ base::FilePath WebAppIntegrationTestDriver::GetShortcu
     base::FilePath shortcut_dir,
     const std::string& app_name,
     const AppId& app_id) {
-#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN)
+#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_WIN) || BUILDFLAG(IS_BSD)
   return override_registration_->test_override->GetShortcutPath(
       profile(), shortcut_dir, app_id, app_name);
 #else
@@ -3982,7 +3982,7 @@ bool WebAppIntegrationTestDriver::IsShortcutAndIconCre
     const AppId& id) {
   base::ScopedAllowBlockingForTesting allow_blocking;
   bool is_shortcut_and_icon_correct = false;
-#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   bool is_shortcut_correct =
       override_registration_->test_override->IsShortcutCreated(profile, id,
                                                                name);
@@ -4026,7 +4026,7 @@ bool WebAppIntegrationTestDriver::DoIconColorsMatch(Pr
     do_icon_colors_match =
         (expected_icon_pixel_color == shortcut_pixel_color_apps_folder.value());
   }
-#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#elif BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   SkColor expected_icon_pixel_color =
       GetSiteConfigurationFromAppName(name).icon_color;
   absl::optional<SkColor> actual_color_install_icon_size =
