--- crates/gpui/src/window.rs.orig	2026-03-26 12:16:36 UTC
+++ crates/gpui/src/window.rs
@@ -4077,7 +4077,7 @@ impl Window {
                 self.modifiers = scroll_wheel.modifiers;
                 PlatformInput::ScrollWheel(scroll_wheel)
             }
-            #[cfg(any(target_os = "linux", target_os = "macos"))]
+            #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
             PlatformInput::Pinch(pinch) => {
                 self.mouse_position = pinch.position;
                 self.modifiers = pinch.modifiers;
