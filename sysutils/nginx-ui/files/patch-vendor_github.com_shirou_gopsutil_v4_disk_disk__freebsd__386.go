--- vendor/github.com/shirou/gopsutil/v4/disk/disk_freebsd_386.go.orig	2025-08-28 06:14:45 UTC
+++ vendor/github.com/shirou/gopsutil/v4/disk/disk_freebsd_386.go
@@ -50,7 +50,7 @@ type devstat struct {
 	Flags         uint32
 	Device_type   uint32
 	Priority      uint32
-	Id            *byte
+	Id            [sizeofPtr]byte
 	Sequence1     uint32
 }
 
