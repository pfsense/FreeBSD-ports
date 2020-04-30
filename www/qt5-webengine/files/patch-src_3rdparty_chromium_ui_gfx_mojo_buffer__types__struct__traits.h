--- src/3rdparty/chromium/ui/gfx/mojo/buffer_types_struct_traits.h.orig	2019-05-23 12:39:34 UTC
+++ src/3rdparty/chromium/ui/gfx/mojo/buffer_types_struct_traits.h
@@ -189,7 +189,7 @@ struct StructTraits<gfx::mojom::GpuMemoryBufferIdDataV
   }
 };
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 template <>
 struct StructTraits<gfx::mojom::NativePixmapPlaneDataView,
                     gfx::NativePixmapPlane> {
@@ -229,7 +229,7 @@ struct StructTraits<gfx::mojom::NativePixmapHandleData
   static bool Read(gfx::mojom::NativePixmapHandleDataView data,
                    gfx::NativePixmapHandle* out);
 };
-#endif  // defined(OS_LINUX)
+#endif  // defined(OS_LINUX) || defined(OS_BSD)
 
 template <>
 struct StructTraits<gfx::mojom::GpuMemoryBufferHandleDataView,
