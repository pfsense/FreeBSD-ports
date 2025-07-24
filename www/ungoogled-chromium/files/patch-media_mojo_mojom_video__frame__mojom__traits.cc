--- media/mojo/mojom/video_frame_mojom_traits.cc.orig	2025-05-06 12:23:00 UTC
+++ media/mojo/mojom/video_frame_mojom_traits.cc
@@ -24,7 +24,7 @@
 #include "ui/gfx/mojom/color_space_mojom_traits.h"
 #include "ui/gfx/mojom/hdr_metadata_mojom_traits.h"
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 #include "base/posix/eintr_wrapper.h"
 #include "media/gpu/buffer_validation.h"
 #endif  // BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
@@ -166,7 +166,7 @@ media::mojom::VideoFrameDataPtr MakeVideoFrameData(
         media::mojom::OpaqueVideoFrameData::New());
   }
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   if (input->storage_type() == media::VideoFrame::STORAGE_DMABUFS) {
     // Duplicates the DMA buffer FDs to a new vector since this cannot take
     // ownership of the FDs in |input| due to constness.
@@ -197,7 +197,7 @@ media::mojom::VideoFrameDataPtr MakeVideoFrameData(
 
 }  // namespace
 
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
 // static
 bool StructTraits<
     media::mojom::ColorPlaneLayoutDataView,
@@ -436,7 +436,7 @@ bool StructTraits<media::mojom::VideoFrameDataView,
     frame = media::VideoFrame::WrapTrackingToken(
         format, *metadata.tracking_token, coded_size, visible_rect,
         natural_size, timestamp);
-#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)
   } else if (data.is_dmabuf_data()) {
     media::mojom::DmabufVideoFrameDataDataView dmabuf_data;
     data.GetDmabufDataDataView(&dmabuf_data);
