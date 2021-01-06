--- include/vulkan/vulkan.hpp.orig	2020-12-07 12:15:47 UTC
+++ include/vulkan/vulkan.hpp
@@ -67,7 +67,7 @@
 #endif
 
 #if VULKAN_HPP_ENABLE_DYNAMIC_LOADER_TOOL == 1
-#  if defined( __linux__ ) || defined( __APPLE__ )
+#  if defined( __unix__ ) || defined( __APPLE__ )
 #    include <dlfcn.h>
 #  elif defined( _WIN32 )
 typedef struct HINSTANCE__ * HINSTANCE;
@@ -92754,7 +92754,7 @@ namespace VULKAN_HPP_NAMESPACE
     {
       if ( !vulkanLibraryName.empty() )
       {
-#  if defined( __linux__ ) || defined( __APPLE__ )
+#  if defined( __unix__ ) || defined( __APPLE__ )
         m_library = dlopen( vulkanLibraryName.c_str(), RTLD_NOW | RTLD_LOCAL );
 #  elif defined( _WIN32 )
         m_library = ::LoadLibraryA( vulkanLibraryName.c_str() );
@@ -92764,7 +92764,7 @@ namespace VULKAN_HPP_NAMESPACE
       }
       else
       {
-#  if defined( __linux__ )
+#  if defined( __unix__ )
         m_library = dlopen( "libvulkan.so", RTLD_NOW | RTLD_LOCAL );
         if ( m_library == nullptr )
         {
@@ -92807,7 +92807,7 @@ namespace VULKAN_HPP_NAMESPACE
     {
       if ( m_library )
       {
-#  if defined( __linux__ ) || defined( __APPLE__ )
+#  if defined( __unix__ ) || defined( __APPLE__ )
         dlclose( m_library );
 #  elif defined( _WIN32 )
         ::FreeLibrary( m_library );
@@ -92820,7 +92820,7 @@ namespace VULKAN_HPP_NAMESPACE
     template <typename T>
     T getProcAddress( const char* function ) const VULKAN_HPP_NOEXCEPT
     {
-#  if defined( __linux__ ) || defined( __APPLE__ )
+#  if defined( __unix__ ) || defined( __APPLE__ )
       return (T)dlsym( m_library, function );
 #  elif defined( _WIN32 )
       return (T)::GetProcAddress( m_library, function );
@@ -92832,7 +92832,7 @@ namespace VULKAN_HPP_NAMESPACE
     bool success() const VULKAN_HPP_NOEXCEPT { return m_library != nullptr; }
 
   private:
-#  if defined( __linux__ ) || defined( __APPLE__ )
+#  if defined( __unix__ ) || defined( __APPLE__ )
     void * m_library;
 #  elif defined( _WIN32 )
     ::HINSTANCE m_library;
