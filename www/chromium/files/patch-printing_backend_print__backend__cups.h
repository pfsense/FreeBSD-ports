--- printing/backend/print_backend_cups.h.orig	2025-09-06 10:01:20 UTC
+++ printing/backend/print_backend_cups.h
@@ -17,7 +17,7 @@
 #include "printing/mojom/print.mojom.h"
 #include "url/gurl.h"
 
-static_assert(BUILDFLAG(IS_LINUX));
+static_assert(BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD));
 
 namespace printing {
 
