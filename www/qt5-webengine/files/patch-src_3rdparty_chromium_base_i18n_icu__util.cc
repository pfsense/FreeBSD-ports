--- src/3rdparty/chromium/base/i18n/icu_util.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/base/i18n/icu_util.cc
@@ -48,7 +48,7 @@
 #include "third_party/icu/source/common/unicode/unistr.h"
 #endif
 
-#if defined(OS_ANDROID) || defined(OS_FUCHSIA) || \
+#if defined(OS_ANDROID) || defined(OS_FUCHSIA) || defined(OS_BSD) || \
     ((defined(OS_LINUX) || defined(OS_CHROMEOS)) && !BUILDFLAG(IS_CHROMECAST))
 #include "third_party/icu/source/i18n/unicode/timezone.h"
 #endif
@@ -345,7 +345,7 @@ void InitializeIcuTimeZone() {
       fuchsia::IntlProfileWatcher::GetPrimaryTimeZoneIdForIcuInitialization();
   icu::TimeZone::adoptDefault(
       icu::TimeZone::createTimeZone(icu::UnicodeString::fromUTF8(zone_id)));
-#elif (defined(OS_LINUX) || defined(OS_CHROMEOS)) && !BUILDFLAG(IS_CHROMECAST)
+#elif (defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)) && !BUILDFLAG(IS_CHROMECAST)
   // To respond to the time zone change properly, the default time zone
   // cache in ICU has to be populated on starting up.
   // See TimeZoneMonitorLinux::NotifyClientsFromImpl().
