--- build/gulpfile.extensions.js.orig	2023-09-06 21:00:17 UTC
+++ build/gulpfile.extensions.js
@@ -238,7 +238,7 @@ exports.compileExtensionMediaBuildTask = compileExtens
 const cleanExtensionsBuildTask = task.define('clean-extensions-build', util.rimraf('.build/extensions'));
 const compileExtensionsBuildTask = task.define('compile-extensions-build', task.series(
 	cleanExtensionsBuildTask,
-	task.define('bundle-marketplace-extensions-build', () => ext.packageMarketplaceExtensionsStream(false).pipe(gulp.dest('.build'))),
+	// task.define('bundle-marketplace-extensions-build', () => ext.packageMarketplaceExtensionsStream(false).pipe(gulp.dest('.build'))),
 	task.define('bundle-extensions-build', () => ext.packageLocalExtensionsStream(false, false).pipe(gulp.dest('.build'))),
 ));
 
