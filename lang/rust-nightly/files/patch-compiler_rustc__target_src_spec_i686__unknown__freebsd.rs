--- compiler/rustc_target/src/spec/i686_unknown_freebsd.rs.orig	2021-10-17 19:23:05 UTC
+++ compiler/rustc_target/src/spec/i686_unknown_freebsd.rs
@@ -2,7 +2,7 @@ pub fn target() -> Target {
 
 pub fn target() -> Target {
     let mut base = super::freebsd_base::opts();
-    base.cpu = "pentium4".into();
+    base.cpu = "pentiumpro".into();
     base.max_atomic_width = Some(64);
     let pre_link_args = base.pre_link_args.entry(LinkerFlavor::Gcc).or_default();
     pre_link_args.push("-m32".into());
