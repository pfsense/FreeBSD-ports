--- libexec/got-index-pack/got-index-pack.c.orig	2022-10-20 14:40:52 UTC
+++ libexec/got-index-pack/got-index-pack.c
@@ -15,6 +15,7 @@
  * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
  */
 
+#include <sys/capsicum.h>
 #include <sys/types.h>
 #include <sys/queue.h>
 #include <sys/mman.h>
@@ -110,6 +111,11 @@ main(int argc, char **argv)
 	/* revoke access to most system calls */
 	if (pledge("stdio recvfd", NULL) == -1) {
 		err = got_error_from_errno("pledge");
+		got_privsep_send_error(&ibuf, err);
+		return 1;
+	}
+	if (cap_enter() == -1) {
+		err = got_error_from_errno("cap_enter");
 		got_privsep_send_error(&ibuf, err);
 		return 1;
 	}
