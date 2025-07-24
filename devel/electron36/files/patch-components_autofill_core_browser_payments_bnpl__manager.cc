--- components/autofill/core/browser/payments/bnpl_manager.cc.orig	2025-04-22 20:15:27 UTC
+++ components/autofill/core/browser/payments/bnpl_manager.cc
@@ -114,7 +114,7 @@ bool BnplManager::ShouldShowBnplSettings() const {
 
 bool BnplManager::ShouldShowBnplSettings() const {
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   const PaymentsDataManager& payments_data_manager =
       payments_autofill_client().GetPaymentsDataManager();
 
@@ -431,7 +431,7 @@ void BnplManager::MaybeUpdateSuggestionsWithBnpl(
       .Run(update_suggestions_result.suggestions, trigger_source);
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   payments_autofill_client().GetPaymentsDataManager().SetAutofillHasSeenBnpl();
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) ||
         // BUILDFLAG(IS_CHROMEOS)
