--- src/FFmpegReader.cpp.orig	2026-03-20 19:34:28 UTC
+++ src/FFmpegReader.cpp
@@ -171,7 +171,7 @@ static enum AVPixelFormat get_hw_dec_format(AVCodecCon
 
 	for (p = pix_fmts; *p != AV_PIX_FMT_NONE; p++) {
 		switch (*p) {
-#if defined(__linux__)
+#if defined(__unix__)
 			// Linux pix formats
 			case AV_PIX_FMT_VAAPI:
 				if (selected == 1) {
@@ -358,7 +358,7 @@ void FFmpegReader::Open() {
 					pCodecCtx->get_format = get_hw_dec_format;
 
 					if (adapter_num < 3 && adapter_num >=0) {
-#if defined(__linux__)
+#if defined(__unix__)
 						snprintf(adapter,sizeof(adapter),"/dev/dri/renderD%d", adapter_num+128);
 						adapter_ptr = adapter;
 						i_decoder_hw = openshot::Settings::Instance()->HARDWARE_DECODER;
@@ -421,12 +421,14 @@ void FFmpegReader::Open() {
 					}
 
 					// Check if it is there and writable
-#if defined(__linux__)
+#if defined(__unix__)
 					if( adapter_ptr != NULL && access( adapter_ptr, W_OK ) == 0 ) {
 #elif defined(_WIN32)
 					if( adapter_ptr != NULL ) {
 #elif defined(__APPLE__)
 					if( adapter_ptr != NULL ) {
+#else
+					if( adapter_ptr != NULL ) {
 #endif
 						ZmqLogger::Instance()->AppendDebugMethod("Decode Device present using device");
 					}
@@ -682,8 +684,13 @@ void FFmpegReader::Open() {
 			AVStream* st = pFormatCtx->streams[i];
 			if (st->codecpar->codec_type == AVMEDIA_TYPE_VIDEO) {
 				// Only inspect the first video stream
+#if LIBAVFORMAT_VERSION_MAJOR >= 62
+				for (int j = 0; j < st->codecpar->nb_coded_side_data; j++) {
+					AVPacketSideData *sd = &st->codecpar->coded_side_data[j];
+#else
 				for (int j = 0; j < st->nb_side_data; j++) {
 					AVPacketSideData *sd = &st->side_data[j];
+#endif
 
 					// Handle rotation metadata (unchanged)
 					if (sd->type == AV_PKT_DATA_DISPLAYMATRIX &&
