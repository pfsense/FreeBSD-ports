--- src/service_inspectors/http2_inspect/http2_hpack_cookie_header_buffer.cc.orig	2025-05-20 07:51:51 UTC
+++ src/service_inspectors/http2_inspect/http2_hpack_cookie_header_buffer.cc
@@ -31,15 +31,15 @@ void Http2CookieHeaderBuffer::append_value(const uint8
     // quoting (RFC 6265) for specifics to cookies.
     if ( !buffer.empty() )
     {
-        buffer += (const uint8_t*)"; ";
+        buffer += (const char*)"; ";
     }
     else
     {
         // let's initialize the buffer to reduce dynamic allocation for std::basic_string<uint8_t>;
         buffer.reserve(Http2CookieHeaderBuffer::initial_buffer_size);
-        buffer = (const uint8_t*)"cookie: ";
+        buffer = (const char*)"cookie: ";
     }
-    buffer.append(start, length);
+    buffer.append((const char *)start, length);
 }
 
 bool Http2CookieHeaderBuffer::append_header_in_decoded_headers(uint8_t* decoded_header_buffer,
@@ -48,7 +48,7 @@ bool Http2CookieHeaderBuffer::append_header_in_decoded
 {
     if ( !buffer.empty() )
     {
-        buffer += (const uint8_t*)"\r\n";
+        buffer += (const char*)"\r\n";
     }
     const u8string& in = buffer;
     const uint32_t in_length = in.length();
