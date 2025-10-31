--- src/codec/SkJpegCodec.cpp.orig	2024-10-24 03:17:23 UTC
+++ src/codec/SkJpegCodec.cpp
@@ -426,16 +426,19 @@ SkISize SkJpegCodec::onGetScaledDimensions(float desir
         num = 1;
     }
 
-    // Set up a fake decompress struct in order to use libjpeg to calculate output dimensions
+    // Set up a fake decompress struct in order to use libjpeg to calculate output dimensions.
+    // This isn't conventional use of libjpeg-turbo but initializing the decompress struct with
+    // jpeg_create_decompress allows for less violation of the API regardless of the version.
     jpeg_decompress_struct dinfo;
-    sk_bzero(&dinfo, sizeof(dinfo));
+    jpeg_create_decompress(&dinfo);
     dinfo.image_width = this->dimensions().width();
     dinfo.image_height = this->dimensions().height();
     dinfo.global_state = fReadyState;
     calc_output_dimensions(&dinfo, num, denom);
+    SkISize outputDimensions = SkISize::Make(dinfo.output_width, dinfo.output_height);
+    jpeg_destroy_decompress(&dinfo);
 
-    // Return the calculated output dimensions for the given scale
-    return SkISize::Make(dinfo.output_width, dinfo.output_height);
+    return outputDimensions;
 }
 
 bool SkJpegCodec::onRewind() {
@@ -534,9 +537,11 @@ bool SkJpegCodec::onDimensionsSupported(const SkISize&
     const unsigned int dstHeight = size.height();
 
     // Set up a fake decompress struct in order to use libjpeg to calculate output dimensions
+    // This isn't conventional use of libjpeg-turbo but initializing the decompress struct with
+    // jpeg_create_decompress allows for less violation of the API regardless of the version.
     // FIXME: Why is this necessary?
     jpeg_decompress_struct dinfo;
-    sk_bzero(&dinfo, sizeof(dinfo));
+    jpeg_create_decompress(&dinfo);
     dinfo.image_width = this->dimensions().width();
     dinfo.image_height = this->dimensions().height();
     dinfo.global_state = fReadyState;
@@ -549,6 +554,7 @@ bool SkJpegCodec::onDimensionsSupported(const SkISize&
 
         // Return a failure if we have tried all of the possible scales
         if (1 == num || dstWidth > dinfo.output_width || dstHeight > dinfo.output_height) {
+            jpeg_destroy_decompress(&dinfo);
             return false;
         }
 
@@ -556,6 +562,7 @@ bool SkJpegCodec::onDimensionsSupported(const SkISize&
         num -= 1;
         calc_output_dimensions(&dinfo, num, denom);
     }
+    jpeg_destroy_decompress(&dinfo);
 
     fDecoderMgr->dinfo()->scale_num = num;
     fDecoderMgr->dinfo()->scale_denom = denom;
