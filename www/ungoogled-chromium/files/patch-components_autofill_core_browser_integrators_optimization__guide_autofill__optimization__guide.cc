--- components/autofill/core/browser/integrators/optimization_guide/autofill_optimization_guide.cc.orig	2025-05-31 17:16:41 UTC
+++ components/autofill/core/browser/integrators/optimization_guide/autofill_optimization_guide.cc
@@ -232,7 +232,7 @@ void AutofillOptimizationGuide::OnDidParseForm(
   }
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   auto bnpl_issuer_allowlist_can_be_loaded =
       [&payments_data_manager](BnplIssuer::IssuerId issuer_id) {
         return base::Contains(payments_data_manager.GetBnplIssuers(), issuer_id,
