--- components/autofill/core/common/autofill_prefs.h.orig	2025-04-22 20:15:27 UTC
+++ components/autofill/core/common/autofill_prefs.h
@@ -32,7 +32,7 @@ inline constexpr char kAutofillAiOptInStatus[] =
 inline constexpr char kAutofillAiOptInStatus[] =
     "autofill.autofill_ai.opt_in_status";
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // Boolean that is true if BNPL on Autofill is enabled.
 inline constexpr char kAutofillBnplEnabled[] = "autofill.bnpl_enabled";
 // Boolean that is true if the user has ever seen a BNPL suggestion.
@@ -208,7 +208,7 @@ bool IsFacilitatedPaymentsEwalletEnabled(const PrefSer
 bool IsFacilitatedPaymentsEwalletEnabled(const PrefService* prefs);
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 void SetAutofillBnplEnabled(PrefService* prefs, bool value);
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) ||
         // BUILDFLAG(IS_CHROMEOS)
@@ -216,7 +216,7 @@ bool IsAutofillBnplEnabled(const PrefService* prefs);
 bool IsAutofillBnplEnabled(const PrefService* prefs);
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 void SetAutofillHasSeenBnpl(PrefService* prefs);
 
 bool HasSeenBnpl(const PrefService* prefs);
