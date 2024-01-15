--- pwc-v4l.c.orig	2006-06-07 20:15:52 UTC
+++ pwc-v4l.c
@@ -67,7 +67,7 @@ int pwc_video_do_ioctl(struct pwc_softc *pdev, unsigne
 		{
 			struct video_capability *caps = arg;
 
-			strncpy(caps->name, pdev->name, sizeof(caps->name) - 1);
+			strncpy(caps->name, device_get_desc(pdev->sc_dev), sizeof(caps->name) - 1);
 			caps->name[sizeof(caps->name) - 1] = '\0';
 			caps->type = VID_TYPE_CAPTURE;
 			caps->channels = 1;
