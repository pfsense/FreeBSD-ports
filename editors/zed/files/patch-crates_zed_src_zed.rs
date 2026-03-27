--- crates/zed/src/zed.rs.orig	2026-03-25 15:03:32 UTC
+++ crates/zed/src/zed.rs
@@ -436,6 +436,7 @@ pub fn initialize_workspace(
         if let Some(specs) = window.gpu_specs() {
             log::info!("Using GPU: {:?}", specs);
             show_software_emulation_warning_if_needed(specs.clone(), window, cx);
+            #[cfg(not(target_os = "freebsd"))]
             crashes::set_gpu_info(specs);
         }
 
