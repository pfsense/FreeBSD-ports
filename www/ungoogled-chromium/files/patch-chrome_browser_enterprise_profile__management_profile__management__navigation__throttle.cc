--- chrome/browser/enterprise/profile_management/profile_management_navigation_throttle.cc.orig	2023-09-17 07:59:53 UTC
+++ chrome/browser/enterprise/profile_management/profile_management_navigation_throttle.cc
@@ -66,8 +66,8 @@ base::flat_map<std::string, SAMLProfileAttributes>& Ge
   // TODO(crbug.com/1445072): Add actual domains with attribute names.
   profile_attributes->insert(std::make_pair(
       "supported.test",
-      SAMLProfileAttributes("placeholderName", "placeholderDomain",
-                            "placeholderToken")));
+      SAMLProfileAttributes(SAMLProfileAttributes{"placeholderName", "placeholderDomain",
+                            "placeholderToken"})));
 
   // Extract domains and attributes from the command line switch.
   const base::CommandLine& command_line =
