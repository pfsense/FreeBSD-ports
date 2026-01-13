--- auth.h.orig	2017-02-28 19:06:15 UTC
+++ auth.h
@@ -28,15 +28,9 @@
  * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
  * SUCH DAMAGE.
  */
+#ifndef	_AUTH_H_
+#define	_AUTH_H_
 
-#ifdef __sun__
-#define	__P(x)	x
-#ifndef	U_INT32_T_DEFINED
-#define	U_INT32_T_DEFINED
-typedef uint32_t u_int32_t;
-#endif
-#endif
-
 #define MD5_DIGESTLENGTH 16
 
 /* secret key information for delayed authentication */
@@ -53,8 +47,10 @@ struct keyinfo {
 	time_t expire;		/* expiration time (0 means forever) */
 };
 
-extern int dhcp6_validate_key __P((struct keyinfo *));
-extern int dhcp6_calc_mac __P((char *, size_t, int, int, size_t,
-    struct keyinfo *));
-extern int dhcp6_verify_mac __P((char *, ssize_t, int, int, size_t,
-    struct keyinfo *));
+int dhcp6_validate_key(struct keyinfo *);
+int dhcp6_calc_mac(char *, size_t, int, int, size_t,
+    struct keyinfo *);
+int dhcp6_verify_mac(char *, ssize_t, int, int, size_t,
+    struct keyinfo *);
+
+#endif
