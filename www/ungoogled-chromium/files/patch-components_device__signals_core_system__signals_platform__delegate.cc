--- components/device_signals/core/system_signals/platform_delegate.cc.orig	2022-10-01 07:40:07 UTC
+++ components/device_signals/core/system_signals/platform_delegate.cc
@@ -11,7 +11,7 @@ namespace device_signals {
 
 bool CustomFilePathComparator::operator()(const base::FilePath& a,
                                           const base::FilePath& b) const {
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
   // On Linux, the file system is case sensitive.
   return a < b;
 #else
