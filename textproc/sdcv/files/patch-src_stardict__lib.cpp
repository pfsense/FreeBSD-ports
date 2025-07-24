--- src/stardict_lib.cpp.orig	2023-04-18 18:47:55 UTC
+++ src/stardict_lib.cpp
@@ -1047,12 +1047,12 @@ bool Libs::LookupSimilarWord(const gchar *sWord, std::
         }
         // Upper the first character and lower others.
         if (!bFound) {
-            gchar *nextchar = g_utf8_next_char(sWord);
+            const gchar *nextchar = g_utf8_next_char(sWord);
             gchar *firstchar = g_utf8_strup(sWord, nextchar - sWord);
-            nextchar = g_utf8_strdown(nextchar, -1);
-            casestr = g_strdup_printf("%s%s", firstchar, nextchar);
+            gchar *nextDownchar = g_utf8_strdown(nextchar, -1);
+            casestr = g_strdup_printf("%s%s", firstchar, nextDownchar);
             g_free(firstchar);
-            g_free(nextchar);
+            g_free(nextDownchar);
             if (strcmp(casestr, sWord)) {
                 if (oLib[iLib]->Lookup(casestr, iWordIndices))
                     bFound = true;
