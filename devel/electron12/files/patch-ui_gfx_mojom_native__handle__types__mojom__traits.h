--- ui/gfx/mojom/native_handle_types_mojom_traits.h.orig	2021-01-07 00:37:29 UTC
+++ ui/gfx/mojom/native_handle_types_mojom_traits.h
@@ -14,13 +14,13 @@
 #include "mojo/public/cpp/system/platform_handle.h"
 #include "ui/gfx/mojom/native_handle_types.mojom-shared.h"
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD) || defined(USE_OZONE)
 #include "ui/gfx/native_pixmap_handle.h"
 #endif
 
 namespace mojo {
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD) || defined(USE_OZONE)
 template <>
 struct COMPONENT_EXPORT(GFX_NATIVE_HANDLE_TYPES_SHARED_MOJOM_TRAITS)
     StructTraits<gfx::mojom::NativePixmapPlaneDataView,
@@ -48,7 +48,7 @@ struct COMPONENT_EXPORT(GFX_NATIVE_HANDLE_TYPES_SHARED
     return pixmap_handle.planes;
   }
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_LINUX) || defined(OS_BSD) || defined(OS_CHROMEOS)
   static uint64_t modifier(const gfx::NativePixmapHandle& pixmap_handle) {
     return pixmap_handle.modifier;
   }
@@ -72,7 +72,7 @@ struct COMPONENT_EXPORT(GFX_NATIVE_HANDLE_TYPES_SHARED
   static bool Read(gfx::mojom::NativePixmapHandleDataView data,
                    gfx::NativePixmapHandle* out);
 };
-#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(USE_OZONE)
+#endif  // defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD) || defined(USE_OZONE)
 
 }  // namespace mojo
 
