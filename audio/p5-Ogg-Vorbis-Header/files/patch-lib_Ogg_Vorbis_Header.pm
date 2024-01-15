--- lib/Ogg/Vorbis/Header.pm.orig	2021-01-04 13:38:15 UTC
+++ lib/Ogg/Vorbis/Header.pm
@@ -7,8 +7,9 @@ use warnings;
 our $VERSION = '0.11';
 
 use Inline C => 'DATA',
+  CC => $ENV{CC},
+  CCFLAGSEX => '-Wno-compound-token-split-by-macro',
   LIBS => '-logg -lvorbis -lvorbisfile',
-  INC => '-I/inc',
   AUTO_INCLUDE => '#include "inc/vcedit.h"',
   AUTO_INCLUDE => '#include "inc/vcedit.c"',
   VERSION => '0.11',
@@ -476,14 +477,14 @@ int write_vorbis (SV *obj)
   if ((fd = fopen(inpath, "rb")) == NULL) {
     perror("Error opening file in Ogg::Vorbis::Header::write\n");
     free(outpath);
-    return &PL_sv_undef;
+    return 0;
   }
 
   if ((fd2 = fopen(outpath, "w+b")) == NULL) {
     perror("Error opening temp file in Ogg::Vorbis::Header::write\n");
     fclose(fd);
     free(outpath);
-    return &PL_sv_undef;
+    return 0;
   }
 
   /* Setup the state and comments structs */
@@ -494,7 +495,7 @@ int write_vorbis (SV *obj)
     fclose(fd2);
     unlink(outpath);
     free(outpath);
-    return &PL_sv_undef;
+    return 0;
   }
   vc = vcedit_comments(state);
 
@@ -526,7 +527,7 @@ int write_vorbis (SV *obj)
     vcedit_clear(state);
     unlink(outpath);
     free(outpath);
-    return &PL_sv_undef;
+    return 0;
   }
 
   fclose(fd);
@@ -536,7 +537,7 @@ int write_vorbis (SV *obj)
     perror("Error copying tempfile in Ogg::Vorbis::Header::add_comment\n");
     unlink(outpath);
     free(outpath);
-    return &PL_sv_undef;
+    return 0;
   }
 
   if ((fd2 = fopen(inpath, "wb")) == NULL) {
@@ -544,7 +545,7 @@ int write_vorbis (SV *obj)
     fclose(fd);
     unlink(outpath);
     free(outpath);
-    return &PL_sv_undef;
+    return 0;
   }
 
   while ((bytes = fread(buffer, 1, BUFFSIZE, fd)) > 0)
