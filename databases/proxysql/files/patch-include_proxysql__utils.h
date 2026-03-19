--- include/proxysql_utils.h.orig	2025-11-08 01:40:32 UTC
+++ include/proxysql_utils.h
@@ -25,19 +25,11 @@
 #define	ETIME	ETIMEDOUT
 #endif
 
-#ifdef CXX17
 template<class...> struct conjunction : std::true_type { };
-template<class B1> struct std::conjunction<B1> : B1 { };
-template<class B1, class... Bn>
-struct std::conjunction<B1, Bn...> 
-    : std::conditional<bool(B1::value), std::conjunction<Bn...>, B1>::type {};
-#else
-template<class...> struct conjunction : std::true_type { };
 template<class B1> struct conjunction<B1> : B1 { };
 template<class B1, class... Bn>
 struct conjunction<B1, Bn...> 
     : std::conditional<bool(B1::value), conjunction<Bn...>, B1>::type {};
-#endif // CXX17
 /**
  * @brief Stores the result of formatting the first parameter with the provided
  *  arguments, into the std::string reference provided in the second parameter.
