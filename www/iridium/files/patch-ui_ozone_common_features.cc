--- ui/ozone/common/features.cc.orig	2023-07-24 14:27:53 UTC
+++ ui/ozone/common/features.cc
@@ -30,7 +30,7 @@ BASE_FEATURE(kWaylandSurfaceSubmissionInPixelCoordinat
 // enabled.
 BASE_FEATURE(kWaylandFractionalScaleV1,
              "WaylandFractionalScaleV1",
-#if BUILDFLAG(IS_LINUX)
+#if BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_BSD)
              base::FEATURE_ENABLED_BY_DEFAULT
 #else
              base::FEATURE_DISABLED_BY_DEFAULT
