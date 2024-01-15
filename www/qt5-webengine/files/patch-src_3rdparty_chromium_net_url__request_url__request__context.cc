--- src/3rdparty/chromium/net/url_request/url_request_context.cc.orig	2021-12-15 16:12:54 UTC
+++ src/3rdparty/chromium/net/url_request/url_request_context.cc
@@ -91,7 +91,7 @@ const HttpNetworkSession::Context* URLRequestContext::
   return &network_session->context();
 }
 
-#if (!defined(OS_WIN) && !defined(OS_LINUX)) || defined(OS_CHROMEOS)
+#if (!defined(OS_WIN) && !defined(OS_LINUX) && !defined(OS_BSD)) || defined(OS_CHROMEOS)
 std::unique_ptr<URLRequest> URLRequestContext::CreateRequest(
     const GURL& url,
     RequestPriority priority,
