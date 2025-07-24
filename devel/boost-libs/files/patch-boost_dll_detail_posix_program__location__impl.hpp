--- boost/dll/detail/posix/program_location_impl.hpp.orig	2025-06-14 19:35:17 UTC
+++ boost/dll/detail/posix/program_location_impl.hpp
@@ -70,7 +70,7 @@ namespace boost { namespace dll { namespace detail {
         mib[2] = KERN_PROC_PATHNAME;
         mib[3] = -1;
         char path[1024];
-        size_t size = sizeof(buf);
+        size_t size = sizeof(path);
         if (sysctl(mib, 4, path, &size, nullptr, 0) == 0)
             return boost::dll::fs::path(path);
 
