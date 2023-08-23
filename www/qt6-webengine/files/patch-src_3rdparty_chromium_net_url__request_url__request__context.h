--- src/3rdparty/chromium/net/url_request/url_request_context.h.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/net/url_request/url_request_context.h
@@ -87,7 +87,7 @@ class NET_EXPORT URLRequestContext final {
 // TODO(crbug.com/1052397): Revisit once build flag switch of lacros-chrome is
 // complete.
 #if !BUILDFLAG(IS_WIN) && \
-    !(BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS))
+    !(BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS_LACROS) || BUILDFLAG(IS_BSD))
   // This function should not be used in Chromium, please use the version with
   // NetworkTrafficAnnotationTag in the future.
   //
