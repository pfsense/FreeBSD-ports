--- src/mmCQL.cpp.orig	2026-03-22 05:31:17 UTC
+++ src/mmCQL.cpp
@@ -1182,7 +1182,7 @@ StatementPtr Parser::ParseUpdate()
 		}
 
 		if (iv)
-			iv->operator()(value);
+			iv->validate_value(value);
 
 		itemValuePairs.emplace_back(item, value);
 
