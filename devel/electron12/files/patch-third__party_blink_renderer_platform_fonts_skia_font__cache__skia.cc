--- third_party/blink/renderer/platform/fonts/skia/font_cache_skia.cc.orig	2021-01-07 00:36:43 UTC
+++ third_party/blink/renderer/platform/fonts/skia/font_cache_skia.cc
@@ -61,7 +61,7 @@ AtomicString ToAtomicString(const SkString& str) {
   return AtomicString::FromUTF8(str.c_str(), str.size());
 }
 
-#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 // This function is called on android or when we are emulating android fonts on
 // linux and the embedder has overriden the default fontManager with
 // WebFontRendering::setSkiaFontMgr.
@@ -84,7 +84,7 @@ AtomicString FontCache::GetFamilyNameForCharacter(
   typeface->getFamilyName(&skia_family_name);
   return ToAtomicString(skia_family_name);
 }
-#endif  // defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#endif  // defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
 
 void FontCache::PlatformInit() {}
 
@@ -229,7 +229,7 @@ sk_sp<SkTypeface> FontCache::CreateTypeface(
   }
 #endif
 
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_WIN)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_WIN) || defined(OS_BSD)
   // On linux if the fontManager has been overridden then we should be calling
   // the embedder provided font Manager rather than calling
   // SkTypeface::CreateFromName which may redirect the call to the default font
@@ -256,7 +256,7 @@ std::unique_ptr<FontPlatformData> FontCache::CreateFon
   std::string name;
 
   sk_sp<SkTypeface> typeface;
-#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS)
+#if defined(OS_ANDROID) || defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_BSD)
   if (alternate_name == AlternateFontName::kLocalUniqueFace &&
       RuntimeEnabledFeatures::FontSrcLocalMatchingEnabled()) {
     typeface = CreateTypefaceFromUniqueName(creation_params);
