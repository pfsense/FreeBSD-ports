--- src/3rdparty/chromium/media/formats/mp2t/es_parser_adts.cc.orig	2018-11-13 18:25:11 UTC
+++ src/3rdparty/chromium/media/formats/mp2t/es_parser_adts.cc
@@ -63,11 +63,11 @@ bool EsParserAdts::LookForAdtsFrame(AdtsFrame* adts_fr
   const uint8_t* es;
   es_queue_->Peek(&es, &es_size);
 
-  int max_offset = es_size - kADTSHeaderMinSize;
-  if (max_offset <= 0)
+  int _max_offset = es_size - kADTSHeaderMinSize;
+  if (_max_offset <= 0)
     return false;
 
-  for (int offset = 0; offset < max_offset; offset++) {
+  for (int offset = 0; offset < _max_offset; offset++) {
     const uint8_t* cur_buf = &es[offset];
     if (!isAdtsSyncWord(cur_buf))
       continue;
@@ -107,7 +107,7 @@ bool EsParserAdts::LookForAdtsFrame(AdtsFrame* adts_fr
     return true;
   }
 
-  es_queue_->Pop(max_offset);
+  es_queue_->Pop(_max_offset);
   return false;
 }
 
