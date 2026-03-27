--- crates/gpui/src/elements/div.rs.orig	2026-03-26 12:09:24 UTC
+++ crates/gpui/src/elements/div.rs
@@ -15,7 +15,7 @@
 //! and Tailwind-like styling that you can use to build your own custom elements. Div is
 //! constructed by combining these two systems into an all-in-one element.
 
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 use crate::PinchEvent;
 use crate::{
     AbsoluteLength, Action, AnyDrag, AnyElement, AnyTooltip, AnyView, App, Bounds, ClickEvent,
@@ -361,7 +361,7 @@ impl Interactivity {
     /// On Windows, pinch gestures are simulated as scroll wheel events with Ctrl held.
     ///
     /// See [`Context::listener`](crate::Context::listener) to get access to a view's state from this callback.
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     pub fn on_pinch(&mut self, listener: impl Fn(&PinchEvent, &mut Window, &mut App) + 'static) {
         self.pinch_listeners
             .push(Box::new(move |event, phase, hitbox, window, cx| {
@@ -377,7 +377,7 @@ impl Interactivity {
     /// On Windows, pinch gestures are simulated as scroll wheel events with Ctrl held.
     ///
     /// See [`Context::listener`](crate::Context::listener) to get access to a view's state from this callback.
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     pub fn capture_pinch(
         &mut self,
         listener: impl Fn(&PinchEvent, &mut Window, &mut App) + 'static,
@@ -675,12 +675,12 @@ impl Interactivity {
         self.hitbox_behavior = HitboxBehavior::BlockMouseExceptScroll;
     }
 
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     fn has_pinch_listeners(&self) -> bool {
         !self.pinch_listeners.is_empty()
     }
 
-    #[cfg(not(any(target_os = "linux", target_os = "macos")))]
+    #[cfg(not(any(target_os = "linux", target_os = "macos", target_os = "freebsd")))]
     fn has_pinch_listeners(&self) -> bool {
         false
     }
@@ -961,7 +961,7 @@ pub trait InteractiveElement: Sized {
     /// On Windows, pinch gestures are simulated as scroll wheel events with Ctrl held.
     ///
     /// See [`Context::listener`](crate::Context::listener) to get access to a view's state from this callback.
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     fn on_pinch(mut self, listener: impl Fn(&PinchEvent, &mut Window, &mut App) + 'static) -> Self {
         self.interactivity().on_pinch(listener);
         self
@@ -974,7 +974,7 @@ pub trait InteractiveElement: Sized {
     /// On Windows, pinch gestures are simulated as scroll wheel events with Ctrl held.
     ///
     /// See [`Context::listener`](crate::Context::listener) to get access to a view's state from this callback.
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     fn capture_pinch(
         mut self,
         listener: impl Fn(&PinchEvent, &mut Window, &mut App) + 'static,
@@ -1367,7 +1367,7 @@ pub(crate) type ScrollWheelListener =
 pub(crate) type ScrollWheelListener =
     Box<dyn Fn(&ScrollWheelEvent, DispatchPhase, &Hitbox, &mut Window, &mut App) + 'static>;
 
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 pub(crate) type PinchListener =
     Box<dyn Fn(&PinchEvent, DispatchPhase, &Hitbox, &mut Window, &mut App) + 'static>;
 
@@ -1725,7 +1725,7 @@ pub struct Interactivity {
     pub(crate) mouse_pressure_listeners: Vec<MousePressureListener>,
     pub(crate) mouse_move_listeners: Vec<MouseMoveListener>,
     pub(crate) scroll_wheel_listeners: Vec<ScrollWheelListener>,
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     pub(crate) pinch_listeners: Vec<PinchListener>,
     pub(crate) key_down_listeners: Vec<KeyDownListener>,
     pub(crate) key_up_listeners: Vec<KeyUpListener>,
@@ -2297,7 +2297,7 @@ impl Interactivity {
             })
         }
 
-        #[cfg(any(target_os = "linux", target_os = "macos"))]
+        #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
         for listener in self.pinch_listeners.drain(..) {
             let hitbox = hitbox.clone();
             window.on_mouse_event(move |event: &PinchEvent, phase, window, cx| {
