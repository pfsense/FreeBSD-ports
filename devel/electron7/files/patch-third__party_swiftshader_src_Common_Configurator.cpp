--- third_party/swiftshader/src/Common/Configurator.cpp.orig	2019-12-12 12:49:31 UTC
+++ third_party/swiftshader/src/Common/Configurator.cpp
@@ -42,6 +42,9 @@ namespace sw
 
 	bool Configurator::readFile()
 	{
+#if defined(__FreeBSD__)
+                return false;
+#endif      
 		#if defined(__unix__)
 			if(access(path.c_str(), R_OK) != 0)
 			{
