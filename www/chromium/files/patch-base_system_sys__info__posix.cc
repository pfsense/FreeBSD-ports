--- base/system/sys_info_posix.cc.orig	2025-05-05 10:57:53 UTC
+++ base/system/sys_info_posix.cc
@@ -143,7 +143,7 @@ void GetKernelVersionNumbers(int32_t* major_version,
 
 namespace base {
 
-#if !BUILDFLAG(IS_OPENBSD)
+#if !BUILDFLAG(IS_BSD)
 // static
 int SysInfo::NumberOfProcessors() {
 #if BUILDFLAG(IS_MAC)
@@ -199,7 +199,7 @@ int SysInfo::NumberOfProcessors() {
 
   return cached_num_cpus;
 }
-#endif  // !BUILDFLAG(IS_OPENBSD)
+#endif  // !BUILDFLAG(IS_BSD)
 
 // static
 uint64_t SysInfo::AmountOfVirtualMemory() {
@@ -285,6 +285,8 @@ std::string SysInfo::OperatingSystemArchitecture() {
     arch = "x86";
   } else if (arch == "amd64") {
     arch = "x86_64";
+  } else if (arch == "arm64") {
+    arch = "aarch64";
   } else if (std::string(info.sysname) == "AIX") {
     arch = "ppc64";
   }
