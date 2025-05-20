--- src/service_inspectors/http2_inspect/http2_hpack_cookie_header_buffer.h.orig	2025-05-20 07:49:41 UTC
+++ src/service_inspectors/http2_inspect/http2_hpack_cookie_header_buffer.h
@@ -30,7 +30,7 @@ class Http2CookieHeaderBuffer final
 
 class Http2CookieHeaderBuffer final
 {
-    using u8string = std::basic_string<uint8_t>;
+    using u8string = std::basic_string<char>;
 public:
     void append_value(const uint8_t* start, int32_t length);
     bool append_header_in_decoded_headers(uint8_t* decoded_header_buffer,
@@ -45,7 +45,7 @@ class Http2CookieHeaderBuffer final
     }
 
 private:
-    u8string buffer = (const uint8_t*)"";
+    u8string buffer = (const char*)"";
 
     static const uint32_t initial_buffer_size = 1024;
     static const uint8_t* cookie_key;
