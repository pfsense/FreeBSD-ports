--- components/neterror/resources/neterror.js.orig	2023-03-13 07:33:08 UTC
+++ components/neterror/resources/neterror.js
@@ -137,7 +137,7 @@ function detailsButtonClick() {
 
 let primaryControlOnLeft = true;
 // clang-format off
-// <if expr="is_macosx or is_ios or is_linux or is_chromeos or is_android">
+// <if expr="is_macosx or is_ios or is_posix or is_chromeos or is_android">
 // clang-format on
 primaryControlOnLeft = false;
 // </if>
