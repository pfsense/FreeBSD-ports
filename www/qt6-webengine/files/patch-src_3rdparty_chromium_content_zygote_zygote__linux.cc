--- src/3rdparty/chromium/content/zygote/zygote_linux.cc.orig	2023-03-28 19:45:02 UTC
+++ src/3rdparty/chromium/content/zygote/zygote_linux.cc
@@ -1,6 +1,7 @@
 // Copyright 2012 The Chromium Authors
 // Use of this source code is governed by a BSD-style license that can be
 // found in the LICENSE file.
+#if 0
 
 #include "content/zygote/zygote_linux.h"
 
@@ -696,3 +697,4 @@ void Zygote::HandleReinitializeLoggingRequest(base::Pi
 }
 
 }  // namespace content
+#endif
