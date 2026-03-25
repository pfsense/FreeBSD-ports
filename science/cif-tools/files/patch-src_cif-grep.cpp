--- src/cif-grep.cpp.orig	2026-03-22 05:31:17 UTC
+++ src/cif-grep.cpp
@@ -70,11 +70,11 @@ class statsParser : public cif::sac_parser
 	{
 	}
 
-	void produce_item(std::string_view category, std::string_view item, std::string_view value) override
+	void produce_item(std::string_view category, std::string_view item, cif::item_value value) override
 	{
 		if ((mCat.empty() or cif::iequals(category, mCat)) and
 			(mItem.empty() or cif::iequals(item, mItem)) and
-			std::regex_search(value.begin(), value.end(), mRx) == not mInvertMatch)
+			std::regex_search(value.sv().begin(), value.sv().end(), mRx) == not mInvertMatch)
 		{
 			++mMatches;
 
