--- src/3rdparty/chromium/components/neterror/resources/neterror.js.orig	2020-04-08 09:41:36 UTC
+++ src/3rdparty/chromium/components/neterror/resources/neterror.js
@@ -201,7 +201,7 @@ function setUpCachedButton(buttonStrings) {
 }
 
 let primaryControlOnLeft = true;
-// <if expr="is_macosx or is_ios or is_linux or is_android">
+// <if expr="is_macosx or is_ios or is_linux or is_android or is_bsd">
 primaryControlOnLeft = false;
 // </if>
 
