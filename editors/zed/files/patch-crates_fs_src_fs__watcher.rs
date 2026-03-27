--- crates/fs/src/fs_watcher.rs.orig	2026-03-25 15:03:32 UTC
+++ crates/fs/src/fs_watcher.rs
@@ -71,7 +71,7 @@ impl Watcher for FsWatcher {
                 return Ok(());
             }
         }
-        #[cfg(target_os = "linux")]
+        #[cfg(any(target_os = "linux", target_os = "freebsd"))]
         {
             if self.registrations.lock().contains_key(path) {
                 log::trace!("path to watch is already watched: {path:?}");
@@ -84,7 +84,7 @@ impl Watcher for FsWatcher {
 
         #[cfg(any(target_os = "windows", target_os = "macos"))]
         let mode = notify::RecursiveMode::Recursive;
-        #[cfg(target_os = "linux")]
+        #[cfg(any(target_os = "linux", target_os = "freebsd"))]
         let mode = notify::RecursiveMode::NonRecursive;
 
         let registration_path = path.clone();
