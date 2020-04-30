--- chrome/browser/ui/libgtkui/print_dialog_gtk.cc.orig	2019-09-10 11:13:43 UTC
+++ chrome/browser/ui/libgtkui/print_dialog_gtk.cc
@@ -333,6 +333,7 @@ void PrintDialogGtk::ShowDialog(
   // Since we only generate PDF, only show printers that support PDF.
   // TODO(thestig) Add more capabilities to support?
   GtkPrintCapabilities cap = static_cast<GtkPrintCapabilities>(
+      GTK_PRINT_CAPABILITY_GENERATE_PS |
       GTK_PRINT_CAPABILITY_GENERATE_PDF |
       GTK_PRINT_CAPABILITY_PAGE_SET |
       GTK_PRINT_CAPABILITY_COPIES |
