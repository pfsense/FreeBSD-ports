--- src/3rdparty/chromium/base/files/file_util.h.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/base/files/file_util.h
@@ -278,14 +278,14 @@ BASE_EXPORT bool ExecutableExistsInPath(Environment* e
 BASE_EXPORT bool ExecutableExistsInPath(Environment* env,
                                         const FilePath::StringType& executable);
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_AIX)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_AIX) || defined(OS_BSD)
 // Determine if files under a given |path| can be mapped and then mprotect'd
 // PROT_EXEC. This depends on the mount options used for |path|, which vary
 // among different Linux distributions and possibly local configuration. It also
 // depends on details of kernel--ChromeOS uses the noexec option for /dev/shm
 // but its kernel allows mprotect with PROT_EXEC anyway.
 BASE_EXPORT bool IsPathExecutable(const FilePath& path);
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_AIX)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_AIX) || defined(OS_BSD)
 
 #endif  // OS_POSIX
 
@@ -592,7 +592,7 @@ BASE_EXPORT int GetMaximumPathComponentLength(const ba
 // the directory |path|, in the number of FilePath::CharType, or -1 on failure.
 BASE_EXPORT int GetMaximumPathComponentLength(const base::FilePath& path);
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_AIX)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_AIX) || defined(OS_BSD)
 // Broad categories of file systems as returned by statfs() on Linux.
 enum FileSystemType {
   FILE_SYSTEM_UNKNOWN,  // statfs failed.
