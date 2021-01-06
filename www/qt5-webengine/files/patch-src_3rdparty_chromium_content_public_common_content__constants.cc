--- src/3rdparty/chromium/content/public/common/content_constants.cc.orig	2020-11-07 01:22:36 UTC
+++ src/3rdparty/chromium/content/public/common/content_constants.cc
@@ -46,7 +46,7 @@ const int kDefaultDetachableCancelDelayMs = 30000;
 const char kCorsExemptPurposeHeaderName[] = "Purpose";
 const char kCorsExemptRequestedWithHeaderName[] = "X-Requested-With";
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 const int kLowestRendererOomScore = 300;
 const int kHighestRendererOomScore = 1000;
 
