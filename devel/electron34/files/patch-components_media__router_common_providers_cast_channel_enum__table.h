--- components/media_router/common/providers/cast/channel/enum_table.h.orig	2025-01-27 17:37:37 UTC
+++ components/media_router/common/providers/cast/channel/enum_table.h
@@ -366,7 +366,12 @@ class EnumTable {
 
  private:
 #ifdef ARCH_CPU_64_BITS
+#ifdef __cpp_lib_hardware_interference_size
   alignas(std::hardware_destructive_interference_size)
+#else
+  static constexpr std::size_t hardware_destructive_interference_size = 64;
+  alignas(hardware_destructive_interference_size)
+#endif
 #endif
       std::initializer_list<Entry> data_;
   bool is_sorted_;
