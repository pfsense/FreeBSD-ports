--- components/password_manager/core/browser/login_database_unittest.cc.orig	2023-07-24 14:27:53 UTC
+++ components/password_manager/core/browser/login_database_unittest.cc
@@ -2170,7 +2170,7 @@ TEST_F(LoginDatabaseUndecryptableLoginsTest, DeleteUnd
   base::HistogramTester histogram_tester;
   ASSERT_TRUE(db.Init());
 
-#if BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_CASTOS))
+#if BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_CASTOS)) || BUILDFLAG(IS_BSD)
   // Make sure that we can't get any logins when database is corrupted.
   // Disabling the checks in chromecast because encryption is unavailable.
   std::vector<std::unique_ptr<PasswordForm>> result;
@@ -2197,7 +2197,7 @@ TEST_F(LoginDatabaseUndecryptableLoginsTest, DeleteUnd
 #endif
 
 // Check histograms.
-#if BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_CASTOS))
+#if BUILDFLAG(IS_MAC) || (BUILDFLAG(IS_LINUX) && !BUILDFLAG(IS_CASTOS)) || BUILDFLAG(IS_BSD)
   histogram_tester.ExpectUniqueSample(
       "PasswordManager.DeleteUndecryptableLoginsReturnValue",
       metrics_util::DeleteCorruptedPasswordsResult::kSuccessPasswordsDeleted,
@@ -2240,7 +2240,7 @@ TEST_F(LoginDatabaseUndecryptableLoginsTest, KeychainL
 }
 #endif  // BUILDFLAG(IS_MAC)
 
-#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
 // Test getting auto sign in logins when there are undecryptable ones
 TEST_F(LoginDatabaseUndecryptableLoginsTest, GetAutoSignInLogins) {
   std::vector<std::unique_ptr<PasswordForm>> forms;
