--- libs/link/include/ableton/platforms/linux/Clock.hpp.orig	2022-10-18 18:10:34 UTC
+++ libs/link/include/ableton/platforms/linux/Clock.hpp
@@ -53,7 +53,7 @@ class Clock (public)
 };
 
 using ClockMonotonic = Clock<CLOCK_MONOTONIC>;
-using ClockMonotonicRaw = Clock<CLOCK_MONOTONIC_RAW>;
+using ClockMonotonicRaw = Clock<CLOCK_MONOTONIC>;
 
 } // namespace linux
 } // namespace platforms
