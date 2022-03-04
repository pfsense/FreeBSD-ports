diff --git src/confdb/confdb.c src/confdb/confdb.c
index e55f88e4e..81fd3417a 100644
--- src/confdb/confdb.c
+++ src/confdb/confdb.c
@@ -28,6 +28,11 @@
 #include "util/strtonum.h"
 #include "db/sysdb.h"
 
+char *strchrnul(const char *s, int ch) {
+       char *ret = strchr(s, ch);
+       return ret == NULL ? discard_const_p(char, s) + strlen(s) : ret;
+}
+
 #define CONFDB_ZERO_CHECK_OR_JUMP(var, ret, err, label) do { \
     if (!var) { \
         ret = err; \
