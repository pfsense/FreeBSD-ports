--- chrome/browser/extensions/api/enterprise_reporting_private/enterprise_reporting_private_api.cc.orig	2022-05-11 07:16:47 UTC
+++ chrome/browser/extensions/api/enterprise_reporting_private/enterprise_reporting_private_api.cc
@@ -155,7 +155,7 @@ EnterpriseReportingPrivateGetDeviceIdFunction::
 
 // getPersistentSecret
 
-#if !defined(OS_LINUX)
+#if !defined(OS_LINUX) && !defined(OS_BSD)
 
 EnterpriseReportingPrivateGetPersistentSecretFunction::
     EnterpriseReportingPrivateGetPersistentSecretFunction() = default;
