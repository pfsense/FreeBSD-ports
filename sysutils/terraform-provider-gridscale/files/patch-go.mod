--- go.mod.orig	2023-03-24 21:59:16 UTC
+++ go.mod
@@ -3,8 +3,69 @@ module github.com/terraform-providers/terraform-provid
 require (
 	github.com/gridscale/gsclient-go/v3 v3.1.0
 	github.com/hashicorp/terraform-plugin-sdk v1.0.0
+)
+
+require (
+	cloud.google.com/go v0.45.1 // indirect
+	github.com/agext/levenshtein v1.2.2 // indirect
+	github.com/apparentlymart/go-cidr v1.0.1 // indirect
+	github.com/apparentlymart/go-textseg v1.0.0 // indirect
+	github.com/armon/go-radix v1.0.0 // indirect
+	github.com/aws/aws-sdk-go v1.19.39 // indirect
+	github.com/bgentry/go-netrc v0.0.0-20140422174119-9fd32a8b3d3d // indirect
+	github.com/bgentry/speakeasy v0.1.0 // indirect
+	github.com/davecgh/go-spew v1.1.1 // indirect
+	github.com/fatih/color v1.7.0 // indirect
+	github.com/golang/protobuf v1.3.2 // indirect
+	github.com/google/go-cmp v0.3.0 // indirect
+	github.com/google/uuid v1.1.1 // indirect
+	github.com/googleapis/gax-go/v2 v2.0.5 // indirect
+	github.com/hashicorp/errwrap v1.0.0 // indirect
+	github.com/hashicorp/go-cleanhttp v0.5.1 // indirect
+	github.com/hashicorp/go-getter v1.4.0 // indirect
+	github.com/hashicorp/go-hclog v0.9.2 // indirect
+	github.com/hashicorp/go-multierror v1.0.0 // indirect
+	github.com/hashicorp/go-plugin v1.0.1 // indirect
+	github.com/hashicorp/go-safetemp v1.0.0 // indirect
+	github.com/hashicorp/go-uuid v1.0.1 // indirect
+	github.com/hashicorp/go-version v1.2.0 // indirect
+	github.com/hashicorp/golang-lru v0.5.1 // indirect
+	github.com/hashicorp/hcl v0.0.0-20170504190234-a4b07c25de5f // indirect
+	github.com/hashicorp/hcl2 v0.0.0-20190821123243-0c888d1241f6 // indirect
+	github.com/hashicorp/hil v0.0.0-20190212112733-ab17b08d6590 // indirect
+	github.com/hashicorp/logutils v1.0.0 // indirect
+	github.com/hashicorp/terraform-config-inspect v0.0.0-20190821133035-82a99dc22ef4 // indirect
+	github.com/hashicorp/yamux v0.0.0-20181012175058-2f1d1f20f75d // indirect
+	github.com/jmespath/go-jmespath v0.0.0-20180206201540-c2b33e8439af // indirect
+	github.com/konsorten/go-windows-terminal-sequences v1.0.3 // indirect
+	github.com/mattn/go-colorable v0.0.9 // indirect
+	github.com/mattn/go-isatty v0.0.4 // indirect
+	github.com/mitchellh/cli v1.0.0 // indirect
+	github.com/mitchellh/colorstring v0.0.0-20190213212951-d06e56a500db // indirect
+	github.com/mitchellh/copystructure v1.0.0 // indirect
+	github.com/mitchellh/go-homedir v1.1.0 // indirect
+	github.com/mitchellh/go-testing-interface v1.0.0 // indirect
+	github.com/mitchellh/go-wordwrap v1.0.0 // indirect
+	github.com/mitchellh/mapstructure v1.1.2 // indirect
+	github.com/mitchellh/reflectwalk v1.0.1 // indirect
+	github.com/oklog/run v1.0.0 // indirect
+	github.com/posener/complete v1.2.1 // indirect
 	github.com/sirupsen/logrus v1.6.0 // indirect
-	golang.org/x/sys v0.0.0-20200625212154-ddb9806d33ae // indirect
+	github.com/spf13/afero v1.2.2 // indirect
+	github.com/ulikunitz/xz v0.5.5 // indirect
+	github.com/vmihailenco/msgpack v3.3.3+incompatible // indirect
+	github.com/zclconf/go-cty v1.1.0 // indirect
+	github.com/zclconf/go-cty-yaml v1.0.1 // indirect
+	go.opencensus.io v0.22.0 // indirect
+	golang.org/x/crypto v0.0.0-20190820162420-60c769a6c586 // indirect
+	golang.org/x/net v0.0.0-20200421231249-e086a090c8fd // indirect
+	golang.org/x/oauth2 v0.0.0-20190604053449-0f29369cfe45 // indirect
+	golang.org/x/sys v0.6.0 // indirect
+	golang.org/x/text v0.3.2 // indirect
+	google.golang.org/api v0.9.0 // indirect
+	google.golang.org/appengine v1.6.1 // indirect
+	google.golang.org/genproto v0.0.0-20190819201941-24fa4b261c55 // indirect
+	google.golang.org/grpc v1.23.0 // indirect
 )
 
-go 1.13
+go 1.17
