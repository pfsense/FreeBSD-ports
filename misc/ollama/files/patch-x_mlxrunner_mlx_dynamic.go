--- x/mlxrunner/mlx/dynamic.go.orig	2026-03-26 19:59:35.600583000 -0700
+++ x/mlxrunner/mlx/dynamic.go	2026-03-26 20:00:54.082312000 -0700
@@ -72,7 +72,7 @@
 	switch runtime.GOOS {
 	case "windows":
 		libraryName = "mlxc.dll"
-	case "linux":
+	case "linux", "freebsd":
 		libraryName = "libmlxc.so"
 	}
 
@@ -93,7 +93,7 @@
 
 func init() {
 	switch runtime.GOOS {
-	case "darwin", "linux", "windows":
+	case "darwin", "linux", "freebsd", "windows":
 
 	default:
 		return
@@ -126,7 +126,10 @@
 		if eval, err := filepath.EvalSymlinks(exe); err == nil {
 			exe = eval
 		}
-		searchDirs = append(searchDirs, filepath.Dir(exe))
+		exeDir := filepath.Dir(exe)
+		searchDirs = append(searchDirs, exeDir)
+		// On Linux/FreeBSD the installed layout is bin/ollama + lib/ollama/libmlxc.so
+		searchDirs = append(searchDirs, filepath.Join(exeDir, "..", "lib", "ollama"))
 	}
 
 	if cwd, err := os.Getwd(); err == nil {
