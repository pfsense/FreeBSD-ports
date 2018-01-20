--- src/dynamic-preprocessors/appid/luaDetectorApi.c.orig	2018-01-04 19:25:27 UTC
+++ src/dynamic-preprocessors/appid/luaDetectorApi.c
@@ -3838,7 +3838,7 @@ static int createFutureFlow (lua_State *
         return 0;
 }
 
-static const luaL_reg Detector_methods[] = {
+static const luaL_Reg Detector_methods[] = {
   /* Obsolete API names.  No longer use these!  They are here for backward
    * compatibility and will eventually be removed. */
   /*  - "memcmp" is now "matchSimplePattern" (below) */
@@ -4051,7 +4051,7 @@ static int Detector_tostring (
   return 1;
 }
 
-static const luaL_reg Detector_meta[] = {
+static const luaL_Reg Detector_meta[] = {
   {"__gc",       Detector_gc},
   {"__tostring", Detector_tostring},
   {0, 0}
