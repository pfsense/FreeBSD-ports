--- etc/rsconvert/yacc.y.orig	1992-02-10 03:04:17 UTC
+++ etc/rsconvert/yacc.y
@@ -14,6 +14,7 @@
 /* $Id: yacc.y,v 4.0.1.3 92/02/07 11:05:21 cek Exp Locker: cek $ */
 %{
 #include <stdio.h>
+#include <stdlib.h>
 #include "libcommon/common.h"
 
 #define NEWLINE()	WriteNewline()
