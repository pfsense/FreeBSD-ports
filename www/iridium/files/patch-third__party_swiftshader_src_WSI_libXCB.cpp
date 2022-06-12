--- third_party/swiftshader/src/WSI/libXCB.cpp.orig	2022-04-01 07:48:30 UTC
+++ third_party/swiftshader/src/WSI/libXCB.cpp
@@ -55,7 +55,7 @@ LibXcbExports *LibXCB::loadExports()
 		}
 		else
                 {
-			libxcb = loadLibrary("libxcb.so.1");
+			libxcb = loadLibrary("libxcb.so");
 		}
 
 		if(getProcAddress(RTLD_DEFAULT, "xcb_shm_query_version"))  // Search the global scope for pre-loaded XCB library.
