--- pkg/minikube/constants/constants_freebsd.go.orig	2019-10-26 17:48:57 UTC
+++ pkg/minikube/constants/constants_freebsd.go
@@ -0,0 +1,26 @@
+// +build freebsd, !gendocs
+
+/*
+Copyright 2016 The Kubernetes Authors All rights reserved.
+
+Licensed under the Apache License, Version 2.0 (the "License");
+you may not use this file except in compliance with the License.
+You may obtain a copy of the License at
+
+    http://www.apache.org/licenses/LICENSE-2.0
+
+Unless required by applicable law or agreed to in writing, software
+distributed under the License is distributed on an "AS IS" BASIS,
+WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
+See the License for the specific language governing permissions and
+limitations under the License.
+*/
+
+package constants
+
+import (
+	"k8s.io/client-go/util/homedir"
+)
+
+// DefaultMountDir is the default mount dir
+var DefaultMountDir = homedir.HomeDir()
