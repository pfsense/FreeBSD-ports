--- crates/gpui/src/interactive.rs.orig	2026-03-26 12:14:06 UTC
+++ crates/gpui/src/interactive.rs
@@ -476,7 +476,7 @@ impl Default for ScrollDelta {
 /// Note: This event is only available on macOS and Wayland (Linux).
 /// On Windows, pinch gestures are simulated as scroll wheel events with Ctrl held.
 #[derive(Clone, Debug, Default)]
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 pub struct PinchEvent {
     /// The position of the pinch center on the window.
     pub position: Point<Pixels>,
@@ -493,20 +493,20 @@ pub struct PinchEvent {
     pub phase: TouchPhase,
 }
 
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 impl Sealed for PinchEvent {}
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 impl InputEvent for PinchEvent {
     fn to_platform_input(self) -> PlatformInput {
         PlatformInput::Pinch(self)
     }
 }
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 impl GestureEvent for PinchEvent {}
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 impl MouseEvent for PinchEvent {}
 
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 impl Deref for PinchEvent {
     type Target = Modifiers;
 
@@ -675,7 +675,7 @@ pub enum PlatformInput {
     /// The scroll wheel was used.
     ScrollWheel(ScrollWheelEvent),
     /// A pinch gesture was performed.
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     Pinch(PinchEvent),
     /// Files were dragged and dropped onto the window.
     FileDrop(FileDropEvent),
@@ -693,7 +693,7 @@ impl PlatformInput {
             PlatformInput::MousePressure(event) => Some(event),
             PlatformInput::MouseExited(event) => Some(event),
             PlatformInput::ScrollWheel(event) => Some(event),
-            #[cfg(any(target_os = "linux", target_os = "macos"))]
+            #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
             PlatformInput::Pinch(event) => Some(event),
             PlatformInput::FileDrop(event) => Some(event),
         }
@@ -710,7 +710,7 @@ impl PlatformInput {
             PlatformInput::MousePressure(_) => None,
             PlatformInput::MouseExited(_) => None,
             PlatformInput::ScrollWheel(_) => None,
-            #[cfg(any(target_os = "linux", target_os = "macos"))]
+            #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
             PlatformInput::Pinch(_) => None,
             PlatformInput::FileDrop(_) => None,
         }
