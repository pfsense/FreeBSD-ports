--- src/iperf_api.c.orig	2016-05-13 15:04:56 UTC
+++ src/iperf_api.c
@@ -2597,7 +2597,7 @@ iperf_free_stream(struct iperf_stream *s
     close(sp->buffer_fd);
     if (sp->diskfile_fd >= 0)
 	close(sp->diskfile_fd);
-    for (irp = TAILQ_FIRST(&sp->result->interval_results); irp != TAILQ_END(sp->result->interval_results); irp = nirp) {
+    for (irp = TAILQ_FIRST(&sp->result->interval_results); irp != NULL; irp = nirp) {
         nirp = TAILQ_NEXT(irp, irlistentries);
         free(irp);
     }
