--- src/cif-diff.cpp.orig	2026-03-22 05:31:17 UTC
+++ src/cif-diff.cpp
@@ -133,7 +133,7 @@ class templateParser : public cif::sac_parser
 	{
 	}
 
-	void produce_item(std::string_view category, std::string_view item, std::string_view value) override
+	void produce_item(std::string_view category, std::string_view item, cif::item_value value) override
 	{
 		std::ostringstream tag;
 		tag << '_' << category << '.' << item;
@@ -195,7 +195,7 @@ void compareCategories(cif::category &a, cif::category
 
 			tie(tag, compare) = tags[kix];
 
-			d = compare(a[tag].text(), b[tag].text());
+			d = compare(a[tag].sv(), b[tag].sv());
 
 			if (d != 0)
 				break;
@@ -334,10 +334,10 @@ void compareCategories(cif::category &a, cif::category
 
 			// make it an option to compare unapplicable to empty or something
 
-			std::string_view ta = ra[tag].text();
+			std::string_view ta = ra[tag].sv();
 			if (ta == ".")
 				ta = "";
-			std::string_view tb = rb[tag].text();
+			std::string_view tb = rb[tag].sv();
 			if (tb == ".")
 				tb = "";
 
@@ -361,7 +361,7 @@ void compareCategories(cif::category &a, cif::category
 
 	if (not diffs.empty())
 	{
-		std::cout << std::string(mcfp::get_terminal_width(), '-') << '\n'
+		std::cout << std::string(cif::get_terminal_width(), '-') << '\n'
 				  << "Differences in values for category " << a.name() << '\n'
 				  << '\n';
 
