--- src/3rdparty/chromium/base/system/sys_info_posix.cc.orig	2019-05-23 12:39:34 UTC
+++ src/3rdparty/chromium/base/system/sys_info_posix.cc
@@ -38,7 +38,7 @@
 
 namespace {
 
-#if !defined(OS_OPENBSD) && !defined(OS_FUCHSIA)
+#if !defined(OS_BSD) && !defined(OS_FUCHSIA)
 int NumberOfProcessors() {
   // sysconf returns the number of "logical" (not "physical") processors on both
   // Mac and Linux.  So we get the number of max available "logical" processors.
@@ -64,7 +64,7 @@ int NumberOfProcessors() {
 
 base::LazyInstance<base::internal::LazySysInfoValue<int, NumberOfProcessors>>::
     Leaky g_lazy_number_of_processors = LAZY_INSTANCE_INITIALIZER;
-#endif  // !defined(OS_OPENBSD) && !defined(OS_FUCHSIA)
+#endif  // !defined(OS_BSD) && !defined(OS_FUCHSIA)
 
 #if !defined(OS_FUCHSIA)
 int64_t AmountOfVirtualMemory() {
@@ -132,7 +132,7 @@ bool GetDiskSpaceInfo(const base::FilePath& path,
 
 namespace base {
 
-#if !defined(OS_OPENBSD) && !defined(OS_FUCHSIA)
+#if !defined(OS_BSD) && !defined(OS_FUCHSIA)
 int SysInfo::NumberOfProcessors() {
   return g_lazy_number_of_processors.Get().value();
 }
@@ -225,7 +225,9 @@ std::string SysInfo::OperatingSystemArchitecture() {
     arch = "x86";
   } else if (arch == "amd64") {
     arch = "x86_64";
-  } else if (std::string(info.sysname) == "AIX") {
+  } else if (arch == "arm64") {
+    arch = "aarch64";
+  } else if (arch == "powerpc" || arch == "powerpc64") {
     arch = "ppc64";
   }
   return arch;
