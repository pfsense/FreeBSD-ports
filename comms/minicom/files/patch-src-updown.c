--- src/updown.c.orig	2013-12-08 10:25:06 UTC
+++ src/updown.c
@@ -298,7 +298,7 @@ void updown(int what, int nr)
     do_log("%s", cmdline);   /* jl 22.06.97 */
 
   if (P_PFULL(g) == 'N') {
-    win = mc_wopen(10, 7, 70, 13, BSINGLE, stdattr, mfcolor, mbcolor, 1, 0, 1);
+    win = mc_wopen(5, 5, 74, 11, BSINGLE, stdattr, mfcolor, mbcolor, 1, 0, 1);
     snprintf(title, sizeof(title), _("%.30s %s - Press CTRL-C to quit"), P_PNAME(g),
              what == 'U' ? _("upload") : _("download"));
     mc_wtitle(win, TMID, title);
