--- components/autofill/core/common/autofill_prefs.h.orig	2025-05-31 17:16:41 UTC
+++ components/autofill/core/common/autofill_prefs.h
@@ -32,7 +32,7 @@ inline constexpr std::string_view kAutofillAblationSee
 inline constexpr char kAutofillAiOptInStatus[] =
     "autofill.autofill_ai.opt_in_status";
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // Boolean that is true if BNPL on Autofill is enabled.
 inline constexpr char kAutofillBnplEnabled[] = "autofill.bnpl_enabled";
 // Boolean that is true if the user has ever seen a BNPL suggestion.
@@ -214,7 +214,7 @@ void SetFacilitatedPaymentsEwallet(PrefService* prefs,
 bool IsFacilitatedPaymentsEwalletEnabled(const PrefService* prefs);
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 void SetAutofillBnplEnabled(PrefService* prefs, bool value);
 #endif  // BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) ||
         // BUILDFLAG(IS_CHROMEOS)
@@ -222,7 +222,7 @@ void SetAutofillBnplEnabled(PrefService* prefs, bool v
 bool IsAutofillBnplEnabled(const PrefService* prefs);
 
 #if BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_LINUX) || \
-    BUILDFLAG(IS_CHROMEOS)
+    BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 void SetAutofillHasSeenBnpl(PrefService* prefs);
 
 bool HasSeenBnpl(const PrefService* prefs);
