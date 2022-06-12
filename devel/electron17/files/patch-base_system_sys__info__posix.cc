--- base/system/sys_info_posix.cc.orig	2022-05-11 07:16:46 UTC
+++ base/system/sys_info_posix.cc
@@ -252,6 +252,8 @@ std::string SysInfo::OperatingSystemArchitecture() {
     arch = "x86";
   } else if (arch == "amd64") {
     arch = "x86_64";
+  } else if (arch == "arm64") {
+    arch = "aarch64";
   } else if (std::string(info.sysname) == "AIX") {
     arch = "ppc64";
   }
