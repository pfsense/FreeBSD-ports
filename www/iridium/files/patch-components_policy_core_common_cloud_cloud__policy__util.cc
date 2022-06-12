--- components/policy/core/common/cloud/cloud_policy_util.cc.orig	2022-03-28 18:11:04 UTC
+++ components/policy/core/common/cloud/cloud_policy_util.cc
@@ -19,7 +19,7 @@
 #endif
 
 #if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || \
-    BUILDFLAG(IS_APPLE) || BUILDFLAG(IS_FUCHSIA)
+    BUILDFLAG(IS_APPLE) || BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
 #include <pwd.h>
 #include <sys/types.h>
 #include <unistd.h>
@@ -34,10 +34,14 @@
 #import <SystemConfiguration/SCDynamicStoreCopySpecific.h>
 #endif
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD)
 #include <limits.h>  // For HOST_NAME_MAX
 #endif
 
+#if BUILDFLAG(IS_FREEBSD)
+#define HOST_NAME_MAX MAXHOSTNAMELEN
+#endif
+
 #include <utility>
 
 #include "base/check.h"
@@ -77,7 +81,7 @@ namespace em = enterprise_management;
 
 std::string GetMachineName() {
 #if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || \
-    BUILDFLAG(IS_FUCHSIA)
+    BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
   char hostname[HOST_NAME_MAX];
   if (gethostname(hostname, HOST_NAME_MAX) == 0)  // Success.
     return hostname;
@@ -135,7 +139,7 @@ std::string GetMachineName() {
 
 std::string GetOSVersion() {
 #if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_APPLE) || \
-    BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_FUCHSIA)
+    BUILDFLAG(IS_ANDROID) || BUILDFLAG(IS_FUCHSIA) || BUILDFLAG(IS_BSD)
   return base::SysInfo::OperatingSystemVersion();
 #elif BUILDFLAG(IS_WIN)
   base::win::OSInfo::VersionNumber version_number =
@@ -158,7 +162,7 @@ std::string GetOSArchitecture() {
 }
 
 std::string GetOSUsername() {
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_APPLE)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_APPLE) || BUILDFLAG(IS_BSD)
   struct passwd* creds = getpwuid(getuid());
   if (!creds || !creds->pw_name)
     return std::string();
