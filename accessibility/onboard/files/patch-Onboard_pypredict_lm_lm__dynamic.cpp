--- Onboard/pypredict/lm/lm_dynamic.cpp.orig	2025-07-03 16:13:44 UTC
+++ Onboard/pypredict/lm/lm_dynamic.cpp
@@ -17,7 +17,7 @@
  * along with this program. If not, see <http://www.gnu.org/licenses/>.
  */
 
-#include <error.h>
+#include <err.h>
 
 #include "lm_dynamic.h"
 
@@ -91,10 +91,10 @@ LMError DynamicModelBase::load_arpac(const char* filen
                     int ngrams_read = get_num_ngrams(current_level-1);
                     if (ngrams_read != ngrams_expected)
                     {
-                        error (0, 0, "unexpected n-gram count for level %d: "
-                                     "expected %d n-grams, but read %d",
-                              current_level,
-                              ngrams_expected, ngrams_read);
+                        err (0, 0, "unexpected n-gram count for level %d: "
+                                   "expected %d n-grams, but read %d",
+                             current_level,
+                             ngrams_expected, ngrams_read);
                         err_code = ERR_COUNT; // count doesn't match number of unique ngrams
                         break;
                     }
@@ -105,10 +105,10 @@ LMError DynamicModelBase::load_arpac(const char* filen
                     if (ntoks < current_level+1)
                     {
                         err_code = ERR_NUMTOKENS; // too few tokens for cur. level
-                        error (0, 0, "too few tokens for n-gram level %d: "
-                              "line %d, tokens found %d/%d",
-                              current_level,
-                              line_number, ntoks, current_level+1);
+                        err (0, 0, "too few tokens for n-gram level %d: "
+                             "line %d, tokens found %d/%d",
+                             current_level,
+                             line_number, ntoks, current_level+1);
                         break;
                     }
 
