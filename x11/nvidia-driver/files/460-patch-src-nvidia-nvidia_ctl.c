--- src/nvidia/nvidia_ctl.c.orig	2021-01-21 21:50:34 UTC
+++ src/nvidia/nvidia_ctl.c
@@ -13,6 +13,12 @@
 #include "nv.h"
 #include "nv-freebsd.h"
 
+#ifdef NV_SUPPORT_LINUX_COMPAT /* (COMPAT_LINUX || COMPAT_LINUX32) */
+#include <compat/linux/linux_util.h>
+
+const char nvidia_driver_name[] = "nvidia";
+#endif
+
 static d_open_t  nvidia_ctl_open;
 static void nvidia_ctl_dtor(void *arg);
 static d_ioctl_t nvidia_ctl_ioctl;
@@ -138,6 +144,18 @@ static int nvidia_ctl_poll(
 
 int nvidia_ctl_attach(void)
 {
+#ifdef NV_SUPPORT_LINUX_COMPAT
+    struct linux_device_handler nvidia_ctl_linux_handler = {
+        .bsd_driver_name = __DECONST(char *, nvidia_driver_name),
+        .linux_driver_name = __DECONST(char *, nvidia_driver_name),
+        .bsd_device_name = __DECONST(char *, nvidia_ctl_cdevsw.d_name),
+        .linux_device_name = __DECONST(char *, nvidia_ctl_cdevsw.d_name),
+        .linux_major = NV_MAJOR_DEVICE_NUMBER,
+        .linux_minor = 255,
+        .linux_char_device = 1
+    };
+#endif
+
     if (nvidia_count == 0) {
         nvidia_ctl_cdev = make_dev(&nvidia_ctl_cdevsw,
                 CDEV_CTL_MINOR,
@@ -145,6 +163,10 @@ int nvidia_ctl_attach(void)
                 "%s", nvidia_ctl_cdevsw.d_name);
         if (nvidia_ctl_cdev == NULL)
             return ENOMEM;
+
+#ifdef NV_SUPPORT_LINUX_COMPAT
+        (void)linux_device_register_handler(&nvidia_ctl_linux_handler);
+#endif
     }
 
     nvidia_count++;
@@ -153,10 +175,25 @@ int nvidia_ctl_attach(void)
 
 int nvidia_ctl_detach(void)
 {
+#ifdef NV_SUPPORT_LINUX_COMPAT
+    struct linux_device_handler nvidia_ctl_linux_handler = {
+        .bsd_driver_name = __DECONST(char *, nvidia_driver_name),
+        .linux_driver_name = __DECONST(char *, nvidia_driver_name),
+        .bsd_device_name = __DECONST(char *, nvidia_ctl_cdevsw.d_name),
+        .linux_device_name = __DECONST(char *, nvidia_ctl_cdevsw.d_name),
+        .linux_major = NV_MAJOR_DEVICE_NUMBER,
+        .linux_minor = 255,
+        .linux_char_device = 1
+    };
+#endif
     nvidia_count--;
 
-    if (nvidia_count == 0)
+    if (nvidia_count == 0) {
+#ifdef NV_SUPPORT_LINUX_COMPAT
+        (void)linux_device_unregister_handler(&nvidia_ctl_linux_handler);
+#endif
         destroy_dev(nvidia_ctl_cdev);
+    }
 
     return 0;
 }
