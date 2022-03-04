--- src/nvidia/nvidia_linux.c.orig	2021-01-21 21:50:34 UTC
+++ src/nvidia/nvidia_linux.c
@@ -35,21 +35,16 @@ int linux_ioctl_nvidia(
     struct linux_ioctl_args *args
 )
 {
-    struct file *fp;
-    int error;
-    cap_rights_t rights;
-    u_long cmd;
+    static const uint32_t dir[4] = { IOC_VOID, IOC_IN, IOC_OUT, IOC_INOUT };
 
-    error = fget(td, args->fd, cap_rights_init(&rights, CAP_IOCTL), &fp);
-    if (error != 0)
-        return error;
-
-    cmd = args->cmd;
-
-    error = fo_ioctl(fp, cmd, (caddr_t)args->arg, td->td_ucred, td);
-    fdrop(fp, td);
-
-    return error;
+    if ((args->cmd & (1<<29)) != 0) {
+        /* FreeBSD has only 13 bits to encode the size. */
+        printf("nvidia: pid %d (%s): ioctl cmd=0x%x size too large\n",
+            (int)td->td_proc->p_pid, td->td_proc->p_comm, args->cmd);
+        return (EINVAL);
+    }
+    args->cmd = (args->cmd & ~IOC_DIRMASK) | dir[args->cmd >> 30];
+    return (sys_ioctl(td, (struct ioctl_args *)args));
 }
 
 struct linux_ioctl_handler nvidia_handler = {
