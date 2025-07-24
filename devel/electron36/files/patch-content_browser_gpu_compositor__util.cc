--- content/browser/gpu/compositor_util.cc.orig	2025-04-22 20:15:27 UTC
+++ content/browser/gpu/compositor_util.cc
@@ -142,7 +142,7 @@ std::vector<GpuFeatureData> GetGpuFeatureData(
       "video_decode",
       SafeGetFeatureStatus(
           gpu_feature_info, gpu::GPU_FEATURE_TYPE_ACCELERATED_VIDEO_DECODE,
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
           !base::FeatureList::IsEnabled(media::kAcceleratedVideoDecodeLinux) ||
 #endif  // BUILDFLAG(IS_LINUX)
               command_line.HasSwitch(switches::kDisableAcceleratedVideoDecode)),
@@ -154,7 +154,7 @@ std::vector<GpuFeatureData> GetGpuFeatureData(
       "video_encode",
       SafeGetFeatureStatus(
           gpu_feature_info, gpu::GPU_FEATURE_TYPE_ACCELERATED_VIDEO_ENCODE,
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
           !base::FeatureList::IsEnabled(media::kAcceleratedVideoEncodeLinux)),
 #else
           command_line.HasSwitch(switches::kDisableAcceleratedVideoEncode)),
