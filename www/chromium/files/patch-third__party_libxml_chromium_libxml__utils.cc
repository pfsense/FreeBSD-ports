--- third_party/libxml/chromium/libxml_utils.cc.orig	2017-06-05 19:03:28 UTC
+++ third_party/libxml/chromium/libxml_utils.cc
@@ -24,8 +24,7 @@ XmlReader::~XmlReader() {
 
 bool XmlReader::Load(const std::string& input) {
   const int kParseOptions = XML_PARSE_RECOVER |  // recover on errors
-                            XML_PARSE_NONET |    // forbid network access
-                            XML_PARSE_NOXXE;     // no external entities
+                            XML_PARSE_NONET;     // forbid network access
   // TODO(evanm): Verify it's OK to pass NULL for the URL and encoding.
   // The libxml code allows for these, but it's unclear what effect is has.
   reader_ = xmlReaderForMemory(input.data(), static_cast<int>(input.size()),
@@ -35,8 +34,7 @@ bool XmlReader::Load(const std::string& input) {
 
 bool XmlReader::LoadFile(const std::string& file_path) {
   const int kParseOptions = XML_PARSE_RECOVER |  // recover on errors
-                            XML_PARSE_NONET |    // forbid network access
-                            XML_PARSE_NOXXE;     // no external entities
+                            XML_PARSE_NONET;     // forbid network access
   reader_ = xmlReaderForFile(file_path.c_str(), NULL, kParseOptions);
   return reader_ != NULL;
 }
