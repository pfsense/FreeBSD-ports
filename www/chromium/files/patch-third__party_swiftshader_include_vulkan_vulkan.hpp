--- third_party/swiftshader/include/vulkan/vulkan.hpp.orig	2021-07-19 18:47:29 UTC
+++ third_party/swiftshader/include/vulkan/vulkan.hpp
@@ -67,7 +67,7 @@
 #endif
 
 #if VULKAN_HPP_ENABLE_DYNAMIC_LOADER_TOOL == 1
-#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ )
+#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ ) || defined(__FreeBSD__)
 #    include <dlfcn.h>
 #  elif defined( _WIN32 )
 typedef struct HINSTANCE__ * HINSTANCE;
@@ -123090,7 +123090,7 @@ namespace VULKAN_HPP_NAMESPACE
     {
       if ( !vulkanLibraryName.empty() )
       {
-#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ )
+#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ ) || defined(__FreeBSD__)
         m_library = dlopen( vulkanLibraryName.c_str(), RTLD_NOW | RTLD_LOCAL );
 #  elif defined( _WIN32 )
         m_library = ::LoadLibraryA( vulkanLibraryName.c_str() );
@@ -123100,7 +123100,7 @@ namespace VULKAN_HPP_NAMESPACE
       }
       else
       {
-#  if defined( __linux__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ )
+#  if defined( __linux__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ ) || defined(__FreeBSD__)
         m_library = dlopen( "libvulkan.so", RTLD_NOW | RTLD_LOCAL );
         if ( m_library == nullptr )
         {
@@ -123144,7 +123144,7 @@ namespace VULKAN_HPP_NAMESPACE
     {
       if ( m_library )
       {
-#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ )
+#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ ) || defined(__FreeBSD__)
         dlclose( m_library );
 #  elif defined( _WIN32 )
         ::FreeLibrary( m_library );
@@ -123157,7 +123157,7 @@ namespace VULKAN_HPP_NAMESPACE
     template <typename T>
     T getProcAddress( const char * function ) const VULKAN_HPP_NOEXCEPT
     {
-#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ )
+#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ ) || defined(__FreeBSD__)
       return (T)dlsym( m_library, function );
 #  elif defined( _WIN32 )
       return ( T )::GetProcAddress( m_library, function );
@@ -123172,7 +123172,7 @@ namespace VULKAN_HPP_NAMESPACE
     }
 
   private:
-#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ )
+#  if defined( __linux__ ) || defined( __APPLE__ ) || defined( __QNXNTO__ ) || defined( __Fuchsia__ ) || defined(__FreeBSD__)
     void * m_library;
 #  elif defined( _WIN32 )
     ::HINSTANCE m_library;
