--- src/3rdparty/chromium/content/browser/devtools/protocol/system_info_handler.cc.orig	2024-05-21 18:07:39 UTC
+++ src/3rdparty/chromium/content/browser/devtools/protocol/system_info_handler.cc
@@ -51,7 +51,7 @@ std::unique_ptr<SystemInfo::Size> GfxSizeToSystemInfoS
 // 1046598, and 1153667.
 // Windows builds need more time -- see Issue 873112 and 1004472.
 // Mac builds need more time - see Issue angleproject:6182.
-#if ((BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS)) && !defined(NDEBUG)) || \
+#if ((BUILDFLAG(IS_LINUX) || BUILDFLAG(IS_CHROMEOS) || BUILDFLAG(IS_BSD)) && !defined(NDEBUG)) || \
     BUILDFLAG(IS_WIN) || BUILDFLAG(IS_MAC) || BUILDFLAG(IS_OZONE)
 static constexpr int kGPUInfoWatchdogTimeoutMultiplierOS = 3;
 #else
