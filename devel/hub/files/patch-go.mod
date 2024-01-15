--- go.mod.orig	2023-03-17 17:04:08 UTC
+++ go.mod
@@ -1,21 +1,25 @@
 module github.com/github/hub
 
-go 1.11
+go 1.17
 
 require (
 	github.com/BurntSushi/toml v0.3.0
 	github.com/atotto/clipboard v0.0.0-20171229224153-bc5958e1c833
 	github.com/bmizerany/assert v0.0.0-20160611221934-b7ed37b82869
 	github.com/kballard/go-shellquote v0.0.0-20170619183022-cd60e84ee657
-	github.com/kr/pretty v0.0.0-20160823170715-cfb55aafdaf3 // indirect
-	github.com/kr/text v0.0.0-20160504234017-7cafcd837844 // indirect
 	github.com/mattn/go-colorable v0.0.9
 	github.com/mattn/go-isatty v0.0.3
 	github.com/mitchellh/go-homedir v0.0.0-20161203194507-b8bc1bf76747
 	github.com/russross/blackfriday v0.0.0-20180526075726-670777b536d3
-	github.com/shurcooL/sanitized_anchor_name v0.0.0-20170918181015-86672fcb3f95 // indirect
 	golang.org/x/crypto v0.0.0-20190308221718-c2843e01d9a2
 	golang.org/x/net v0.0.0-20191002035440-2ec189313ef0
-	golang.org/x/sys v0.0.0-20190531175056-4c3a928424d2 // indirect
 	gopkg.in/yaml.v2 v2.0.0-20190319135612-7b8349ac747c
+)
+
+require (
+	github.com/kr/pretty v0.0.0-20160823170715-cfb55aafdaf3 // indirect
+	github.com/kr/text v0.0.0-20160504234017-7cafcd837844 // indirect
+	github.com/shurcooL/sanitized_anchor_name v0.0.0-20170918181015-86672fcb3f95 // indirect
+	golang.org/x/sys v0.6.0 // indirect
+	golang.org/x/text v0.3.0 // indirect
 )
