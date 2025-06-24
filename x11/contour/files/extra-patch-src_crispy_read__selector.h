--- src/crispy/read_selector.h.orig	2025-06-02 12:31:49 UTC
+++ src/crispy/read_selector.h
@@ -108,7 +108,7 @@ class posix_read_selector
         if (timeout.has_value())
         {
             tv = std::make_unique<timeval>(
-                timeval { .tv_sec = timeout->count() / 1000,
+                timeval { .tv_sec = static_cast<int>(timeout->count() / 1000),
                           .tv_usec = static_cast<int>((timeout->count() % 1000) * 1000) });
         }
 
