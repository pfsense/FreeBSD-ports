--- src/xvjpeg.c.orig	2023-07-17 01:25:42 UTC
+++ src/xvjpeg.c
@@ -699,7 +699,7 @@ L2:
         if ((cmy = *q++ - k) < 0) cmy = 0; *p++ = cmy; /* R */
         if ((cmy = *q++ - k) < 0) cmy = 0; *p++ = cmy; /* G */
         if ((cmy = *q++ - k) < 0) cmy = 0; *p++ = cmy; /* B */
-      } while (++q <= pic_end);
+      } while (++q < pic_end);
     }
     else { /* assume normal data */
       register byte *q = pic;
@@ -710,7 +710,7 @@ L2:
         if ((cmy = k - *q++) < 0) cmy = 0; *p++ = cmy; /* R */
         if ((cmy = k - *q++) < 0) cmy = 0; *p++ = cmy; /* G */
         if ((cmy = k - *q++) < 0) cmy = 0; *p++ = cmy; /* B */
-      } while (++q <= pic_end);
+      } while (++q < pic_end);
     }
     pic = realloc(pic,p-pic); /* Release extra storage */
   }
