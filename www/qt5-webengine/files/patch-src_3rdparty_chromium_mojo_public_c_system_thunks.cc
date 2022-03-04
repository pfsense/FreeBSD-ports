--- src/3rdparty/chromium/mojo/public/c/system/thunks.cc.orig	2020-04-08 09:41:36 UTC
+++ src/3rdparty/chromium/mojo/public/c/system/thunks.cc
@@ -15,7 +15,7 @@
 #include "build/build_config.h"
 #include "mojo/public/c/system/core.h"
 
-#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_WIN)
+#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_WIN) || defined(OS_BSD)
 #include "base/environment.h"
 #include "base/files/file_path.h"
 #include "base/optional.h"
@@ -58,7 +58,7 @@ namespace mojo {
 class CoreLibraryInitializer {
  public:
   CoreLibraryInitializer(const MojoInitializeOptions* options) {
-#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_WIN)
+#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_WIN) || defined(OS_BSD)
     bool application_provided_path = false;
     base::Optional<base::FilePath> library_path;
     if (options && options->struct_size >= sizeof(*options) &&
@@ -77,7 +77,7 @@ class CoreLibraryInitializer {
 
     if (!library_path) {
       // Default to looking for the library in the current working directory.
-#if defined(OS_CHROMEOS) || defined(OS_LINUX)
+#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_BSD)
       const base::FilePath::CharType kDefaultLibraryPathValue[] =
           FILE_PATH_LITERAL("./libmojo_core.so");
 #elif defined(OS_WIN)
@@ -127,16 +127,16 @@ class CoreLibraryInitializer {
 
     CHECK_GT(g_thunks.size, 0u)
         << "Invalid mojo_core library: " << library_path->value();
-#else   // defined(OS_CHROMEOS) || defined(OS_LINUX)
+#else   // defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_BSD)
     NOTREACHED()
         << "Dynamic mojo_core loading is not supported on this platform.";
-#endif  // defined(OS_CHROMEOS) || defined(OS_LINUX)
+#endif  // defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_BSD)
   }
 
   ~CoreLibraryInitializer() = default;
 
  private:
-#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_WIN)
+#if defined(OS_CHROMEOS) || defined(OS_LINUX) || defined(OS_WIN) || defined(OS_BSD)
   base::Optional<base::ScopedNativeLibrary> library_;
 #endif
 
