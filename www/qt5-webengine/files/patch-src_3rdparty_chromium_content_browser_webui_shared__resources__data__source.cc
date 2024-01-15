--- src/3rdparty/chromium/content/browser/webui/shared_resources_data_source.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/content/browser/webui/shared_resources_data_source.cc
@@ -120,13 +120,13 @@ const std::map<int, std::string> CreateMojoResourceIdT
          "mojo/mojo/public/mojom/base/text_direction.mojom.html"},
         {IDR_MOJO_TEXT_DIRECTION_MOJOM_LITE_JS,
          "mojo/mojo/public/mojom/base/text_direction.mojom-lite.js"},
-#if defined(OS_WIN) || defined(OS_MAC) || defined(OS_LINUX) || \
+#if defined(OS_WIN) || defined(OS_MAC) || defined(OS_LINUX) || defined(OS_BSD) || \
     defined(OS_CHROMEOS) || defined(OS_ANDROID)
         {IDR_MOJO_TIME_MOJOM_HTML,
          "mojo/mojo/public/mojom/base/time.mojom.html"},
         {IDR_MOJO_TIME_MOJOM_LITE_JS,
          "mojo/mojo/public/mojom/base/time.mojom-lite.js"},
-#endif  // defined(OS_WIN) || defined(OS_MAC) || defined(OS_LINUX) ||
+#endif  // defined(OS_WIN) || defined(OS_MAC) || defined(OS_LINUX) || defined(OS_BSD) ||
         // defined(OS_CHROMEOS) || defined(OS_ANDROID)
   };
 }
