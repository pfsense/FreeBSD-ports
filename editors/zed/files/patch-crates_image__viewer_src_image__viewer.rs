--- crates/image_viewer/src/image_viewer.rs.orig	2026-03-26 12:17:33 UTC
+++ crates/image_viewer/src/image_viewer.rs
@@ -6,7 +6,7 @@ use file_icons::FileIcons;
 use anyhow::Context as _;
 use editor::{EditorSettings, items::entry_git_aware_label_color};
 use file_icons::FileIcons;
-#[cfg(any(target_os = "linux", target_os = "macos"))]
+#[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
 use gpui::PinchEvent;
 use gpui::{
     AnyElement, App, Bounds, Context, DispatchPhase, Element, ElementId, Entity, EventEmitter,
@@ -263,7 +263,7 @@ impl ImageView {
         }
     }
 
-    #[cfg(any(target_os = "linux", target_os = "macos"))]
+    #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
     fn handle_pinch(&mut self, event: &PinchEvent, _window: &mut Window, cx: &mut Context<Self>) {
         let zoom_factor = 1.0 + event.delta;
         self.set_zoom(self.zoom_level * zoom_factor, Some(event.position), cx);
@@ -690,7 +690,7 @@ impl Render for ImageView {
             .relative()
             .bg(cx.theme().colors().editor_background)
             .child({
-                #[cfg(any(target_os = "linux", target_os = "macos"))]
+                #[cfg(any(target_os = "linux", target_os = "macos", target_os = "freebsd"))]
                 let container = div()
                     .id("image-container")
                     .size_full()
@@ -709,7 +709,7 @@ impl Render for ImageView {
                     .on_mouse_move(cx.listener(Self::handle_mouse_move))
                     .child(ImageContentElement::new(cx.entity()));
 
-                #[cfg(not(any(target_os = "linux", target_os = "macos")))]
+                #[cfg(not(any(target_os = "linux", target_os = "macos", target_os = "freebsd")))]
                 let container = div()
                     .id("image-container")
                     .size_full()
