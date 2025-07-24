--- base/files/file_util_posix.cc.orig	2025-05-05 10:57:53 UTC
+++ base/files/file_util_posix.cc
@@ -934,6 +934,7 @@ bool CreateNewTempDirectory(const FilePath::StringType
 bool CreateDirectoryAndGetError(const FilePath& full_path, File::Error* error) {
   ScopedBlockingCall scoped_blocking_call(
       FROM_HERE, BlockingType::MAY_BLOCK);  // For call to mkdir().
+  const FilePath kFileSystemRoot("/");
 
   // Avoid checking subdirs if directory already exists.
   if (DirectoryExists(full_path)) {
@@ -943,8 +944,8 @@ bool CreateDirectoryAndGetError(const FilePath& full_p
   // Collect a list of all missing directories.
   std::vector<FilePath> missing_subpaths({full_path});
   FilePath last_path = full_path;
-  for (FilePath path = full_path.DirName(); path.value() != last_path.value();
-       path = path.DirName()) {
+  for (FilePath path = full_path.DirName(); (path.value() != last_path.value() &&
+       (path != kFileSystemRoot)); path = path.DirName()) {
     if (DirectoryExists(path)) {
       break;
     }
@@ -962,21 +963,14 @@ bool CreateDirectoryAndGetError(const FilePath& full_p
     }
 #endif  // BUILDFLAG(IS_CHROMEOS)
 
-    if (mkdir(subpath.value().c_str(), mode) == 0) {
-      continue;
-    }
-    // Mkdir failed, but it might have failed with EEXIST, or some other error
-    // due to the directory appearing out of thin air. This can occur if
-    // two processes are trying to create the same file system tree at the same
-    // time. Check to see if it exists and make sure it is a directory.
-    int saved_errno = errno;
-    if (!DirectoryExists(subpath)) {
-      if (error) {
+    if ((mkdir(subpath.value().c_str(), mode) == -1) &&
+        ((full_path != subpath) ? (errno != ENOENT) : (-1))) {
+      int saved_errno = errno;
+      if (error)
         *error = File::OSErrorToFileError(saved_errno);
-      }
-      errno = saved_errno;
       return false;
     }
+    errno = 0;
   }
   return true;
 }
