--- remoting/host/host_attributes.cc.orig	2019-12-12 12:39:55 UTC
+++ remoting/host/host_attributes.cc
@@ -124,7 +124,7 @@ std::string GetHostAttributes() {
       media::InitializeMediaFoundation()) {
     result.push_back("HWEncoder");
   }
-#elif defined(OS_LINUX)
+#elif defined(OS_LINUX) || defined(OS_BSD)
   result.push_back("HWEncoder");
 #endif
 
