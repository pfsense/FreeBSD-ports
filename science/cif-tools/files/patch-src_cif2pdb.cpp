--- src/cif2pdb.cpp.orig	2026-03-22 05:31:17 UTC
+++ src/cif2pdb.cpp
@@ -92,9 +92,9 @@ int pr_main(int argc, char *argv[])
 
 	// Load dict, if any
 	if (config.has("dict"))
-		f.front().set_validator(&cif::validator_factory::instance().get(config.get<std::string>("dict")));
+		f.front().set_validator(cif::validator_factory::instance().get(config.get<std::string>("dict")));
 	else if (f.front().get_validator() == nullptr)
-		f.front().set_validator(&cif::validator_factory::instance().get("mmcif_pdbx.dic"));
+		f.front().set_validator(cif::validator_factory::instance().get("mmcif_pdbx.dic"));
 
 	if (f.empty() or (not config.has("no-validate") and not f.is_valid()))
 	{
