--- include/hyprutils/string/Numeric.hpp.orig	2026-03-22 11:10:17 UTC
+++ include/hyprutils/string/Numeric.hpp
@@ -5,6 +5,12 @@
 #include <charconv>
 #include <concepts>
 
+#if defined(_LIBCPP_VERSION) && _LIBCPP_VERSION < 200000
+#include <string>
+#include <cstdlib>
+#include <cerrno>
+#endif
+
 namespace Hyprutils::String {
 
     enum eNumericParseResult : uint8_t {
@@ -40,6 +46,47 @@ namespace Hyprutils::String {
             }
         }
 
+#if defined(_LIBCPP_VERSION) && _LIBCPP_VERSION < 200000
+        // libc++ < 20 does not implement std::from_chars for floating point types
+        if constexpr (std::floating_point<T>) {
+            std::string_view ts = sv;
+            if (ts.starts_with('+') || ts.starts_with('-'))
+                ts.remove_prefix(1);
+            if (ts.size() >= 2 && ts[0] == '0' && (ts[1] == 'x' || ts[1] == 'X'))
+                return std::unexpected(NUMERIC_PARSE_GARBAGE);
+
+            std::string s{sv};
+            char*       endptr = nullptr;
+            errno              = 0;
+
+            if constexpr (std::same_as<T, float>)
+                value = std::strtof(s.c_str(), &endptr);
+            else if constexpr (std::same_as<T, double>)
+                value = std::strtod(s.c_str(), &endptr);
+            else
+                value = std::strtold(s.c_str(), &endptr);
+
+            if (endptr == s.c_str())
+                return std::unexpected(NUMERIC_PARSE_BAD);
+            if (errno == ERANGE)
+                return std::unexpected(NUMERIC_PARSE_OUT_OF_RANGE);
+            if (endptr != s.c_str() + s.size())
+                return std::unexpected(NUMERIC_PARSE_GARBAGE);
+
+            return value;
+        } else {
+            const auto [ptr, ec] = std::from_chars(sv.data(), sv.data() + sv.size(), value);
+
+            if (ec == std::errc::invalid_argument)
+                return std::unexpected(NUMERIC_PARSE_BAD);
+            if (ec == std::errc::result_out_of_range)
+                return std::unexpected(NUMERIC_PARSE_OUT_OF_RANGE);
+            if (ptr != sv.data() + sv.size())
+                return std::unexpected(NUMERIC_PARSE_GARBAGE);
+
+            return value;
+        }
+#else
         const auto [ptr, ec] = std::from_chars(sv.data(), sv.data() + sv.size(), value);
 
         if (ec == std::errc::invalid_argument)
@@ -50,5 +97,6 @@ namespace Hyprutils::String {
             return std::unexpected(NUMERIC_PARSE_GARBAGE);
 
         return value;
+#endif
     }
-};
\ No newline at end of file
+};
