--- ui/gfx/mojom/native_handle_types_mojom_traits.h.orig	2022-05-11 07:17:07 UTC
+++ ui/gfx/mojom/native_handle_types_mojom_traits.h
@@ -15,7 +15,7 @@
 #include "third_party/abseil-cpp/absl/types/optional.h"
 #include "ui/gfx/mojom/native_handle_types.mojom-shared.h"
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE) || defined(OS_BSD)
 #include "ui/gfx/native_pixmap_handle.h"
 #endif
 
@@ -25,7 +25,7 @@
 
 namespace mojo {
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE) || defined(OS_BSD)
 template <>
 struct COMPONENT_EXPORT(GFX_NATIVE_HANDLE_TYPES_SHARED_MOJOM_TRAITS)
     StructTraits<gfx::mojom::NativePixmapPlaneDataView,
@@ -53,7 +53,7 @@ struct COMPONENT_EXPORT(GFX_NATIVE_HANDLE_TYPES_SHARED
     return pixmap_handle.planes;
   }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   static uint64_t modifier(const gfx::NativePixmapHandle& pixmap_handle) {
     return pixmap_handle.modifier;
   }
