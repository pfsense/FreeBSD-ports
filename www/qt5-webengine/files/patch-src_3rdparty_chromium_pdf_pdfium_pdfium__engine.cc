--- src/3rdparty/chromium/pdf/pdfium/pdfium_engine.cc.orig	2020-03-16 14:04:24 UTC
+++ src/3rdparty/chromium/pdf/pdfium/pdfium_engine.cc
@@ -52,7 +52,7 @@
 #include "ui/gfx/geometry/rect.h"
 #include "v8/include/v8.h"
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
 #include "pdf/pdfium/pdfium_font_linux.h"
 #endif
 
@@ -377,7 +377,7 @@ void InitializeSDK(bool enable_v8) {
   config.m_v8EmbedderSlot = gin::kEmbedderPDFium;
   FPDF_InitLibraryWithConfig(&config);
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   InitializeLinuxFontMapper();
 #endif
 
@@ -411,7 +411,7 @@ PDFiumEngine::PDFiumEngine(PDFEngine::Client* client, 
   IFSDK_PAUSE::user = nullptr;
   IFSDK_PAUSE::NeedToPauseNow = Pause_NeedToPauseNow;
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   // PreviewModeClient does not know its pp::Instance.
   SetLastInstance(client_->GetPluginInstance());
 #endif
@@ -867,7 +867,7 @@ pp::Buffer_Dev PDFiumEngine::PrintPagesAsRasterPdf(
 
   KillFormFocus();
 
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   SetLastInstance(client_->GetPluginInstance());
 #endif
 
@@ -2798,7 +2798,7 @@ bool PDFiumEngine::ContinuePaint(int progressive_index
   DCHECK(image_data);
 
   last_progressive_start_time_ = base::Time::Now();
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   SetLastInstance(client_->GetPluginInstance());
 #endif
 
@@ -3292,7 +3292,7 @@ void PDFiumEngine::SetCurrentPage(int index) {
     FORM_DoPageAAction(old_page, form(), FPDFPAGE_AACTION_CLOSE);
   }
   most_visible_page_ = index;
-#if defined(OS_LINUX)
+#if defined(OS_LINUX) || defined(OS_BSD)
   SetLastInstance(client_->GetPluginInstance());
 #endif
   if (most_visible_page_ != -1 && called_do_document_action_) {
