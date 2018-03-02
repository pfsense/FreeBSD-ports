--- src/dynamic-preprocessors/appid/luaDetectorFlowApi.c.orig	2018-01-04 19:34:16 UTC
+++ src/dynamic-preprocessors/appid/luaDetectorFlowApi.c
@@ -497,7 +497,7 @@ static int DetectorFlow_getFlowKey(
 
 
 
-static const luaL_reg DetectorFlow_methods[] = {
+static const luaL_Reg DetectorFlow_methods[] = {
   /* Obsolete API names.  No longer use these!  They are here for backward
    * compatibility and will eventually be removed. */
   /*  - "new" is now "createFlow" (below) */
@@ -544,7 +544,7 @@ static int DetectorFlow_tostring (
   return 1;
 }
 
-static const luaL_reg DetectorFlow_meta[] = {
+static const luaL_Reg DetectorFlow_meta[] = {
   {"__gc",       DetectorFlow_gc},
   {"__tostring", DetectorFlow_tostring},
   {0, 0}
