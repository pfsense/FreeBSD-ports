--- third_party/blink/renderer/platform/fonts/shaping/harfbuzz_shaper_test.cc.orig	2021-01-07 00:36:43 UTC
+++ third_party/blink/renderer/platform/fonts/shaping/harfbuzz_shaper_test.cc
@@ -651,7 +651,7 @@ TEST_P(ShapeParameterTest, MaxGlyphsClusterDevanagari)
   HarfBuzzShaper shaper(string);
   scoped_refptr<ShapeResult> result = ShapeWithParameter(&shaper);
   EXPECT_EQ(length, result->NumCharacters());
-#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_FUCHSIA)
+#if defined(OS_LINUX) || defined(OS_CHROMEOS) || defined(OS_FUCHSIA) || defined(OS_BSD)
   // Linux and Fuchsia use Lohit Devanagari. When using that font the shaper
   // returns 32767 glyphs instead of 32769.
   // TODO(crbug.com/933551): Add Noto Sans Devanagari to
