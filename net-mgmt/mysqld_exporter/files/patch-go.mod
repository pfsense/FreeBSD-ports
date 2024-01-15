--- go.mod.orig	2023-03-25 01:00:23 UTC
+++ go.mod
@@ -1,25 +1,33 @@
 module github.com/prometheus/mysqld_exporter
 
+go 1.17
+
 require (
 	github.com/DATA-DOG/go-sqlmock v1.3.3
+	github.com/go-sql-driver/mysql v1.4.1
+	github.com/prometheus/client_golang v1.0.0
+	github.com/prometheus/client_model v0.0.0-20190129233127-fd36f4220a90
+	github.com/prometheus/common v0.6.0
+	github.com/satori/go.uuid v1.2.0
+	github.com/smartystreets/goconvey v0.0.0-20190330032615-68dc04aab96a
+	gopkg.in/alecthomas/kingpin.v2 v2.2.6
+	gopkg.in/ini.v1 v1.44.0
+)
+
+require (
 	github.com/alecthomas/template v0.0.0-20190718012654-fb15b899a751 // indirect
 	github.com/alecthomas/units v0.0.0-20190717042225-c3de453c63f4 // indirect
-	github.com/go-sql-driver/mysql v1.4.1
+	github.com/beorn7/perks v1.0.0 // indirect
 	github.com/golang/protobuf v1.3.2 // indirect
 	github.com/gopherjs/gopherjs v0.0.0-20190430165422-3e4dfb77656c // indirect
+	github.com/jtolds/gls v4.20.0+incompatible // indirect
 	github.com/konsorten/go-windows-terminal-sequences v1.0.2 // indirect
 	github.com/kr/pretty v0.1.0 // indirect
-	github.com/prometheus/client_golang v1.0.0
-	github.com/prometheus/client_model v0.0.0-20190129233127-fd36f4220a90
-	github.com/prometheus/common v0.6.0
+	github.com/matttproud/golang_protobuf_extensions v1.0.1 // indirect
 	github.com/prometheus/procfs v0.0.3 // indirect
-	github.com/satori/go.uuid v1.2.0
 	github.com/sirupsen/logrus v1.4.2 // indirect
 	github.com/smartystreets/assertions v1.0.0 // indirect
-	github.com/smartystreets/goconvey v0.0.0-20190330032615-68dc04aab96a
-	golang.org/x/sys v0.0.0-20190626221950-04f50cda93cb // indirect
+	golang.org/x/sys v0.6.0 // indirect
 	google.golang.org/appengine v1.6.1 // indirect
-	gopkg.in/alecthomas/kingpin.v2 v2.2.6
 	gopkg.in/check.v1 v1.0.0-20180628173108-788fd7840127 // indirect
-	gopkg.in/ini.v1 v1.44.0
 )
