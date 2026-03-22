--- src/cif-validate.cpp.orig	2026-03-22 05:31:17 UTC
+++ src/cif-validate.cpp
@@ -52,7 +52,7 @@ class dummy_parser : public cif::sac_parser
 	{
 	}
 
-	void produce_item(std::string_view category, std::string_view item, std::string_view value) override
+	void produce_item(std::string_view category, std::string_view item, cif::item_value value) override
 	{
 	}
 };
@@ -112,12 +112,12 @@ int pr_main(int argc, char *argv[])
 		for (auto &db : f)
 		{
 			if (config.count("dict"))
-				db.set_validator(&cif::validator_factory::instance().get(config.get<std::string>("dict")));
+				db.set_validator(cif::validator_factory::instance().get(config.get<std::string>("dict")));
 			else
 				db.load_dictionary();
 			
 			if (db.get_validator() == nullptr)
-				db.set_validator(&cif::validator_factory::instance().get("mmcif_pdbx.dic"));
+				db.set_validator(cif::validator_factory::instance().get("mmcif_pdbx.dic"));
 
 			if (not const_cast<const cif::datablock &>(db).is_valid())
 				result = 1;
