--- timer.h.orig	2017-02-28 19:06:15 UTC
+++ timer.h
@@ -28,6 +28,8 @@
  * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
  * SUCH DAMAGE.
  */
+#ifndef	_TIMER_H_
+#define	_TIMER_H_
 
 /* a < b */
 #define TIMEVAL_LT(a, b) (((a).tv_sec < (b).tv_sec) ||\
@@ -46,17 +48,18 @@ struct dhcp6_timer {
 
 	struct timeval tm;
 
-	struct dhcp6_timer *(*expire) __P((void *));
+	struct dhcp6_timer *(*expire)(void *);
 	void *expire_data;
 };
 
-void dhcp6_timer_init __P((void));
-struct dhcp6_timer *dhcp6_add_timer __P((struct dhcp6_timer *(*) __P((void *)),
-					 void *));
-void dhcp6_set_timer __P((struct timeval *, struct dhcp6_timer *));
-void dhcp6_remove_timer __P((struct dhcp6_timer **));
-struct timeval * dhcp6_check_timer __P((void));
-struct timeval * dhcp6_timer_rest __P((struct dhcp6_timer *));
+void dhcp6_timer_init(void);
+struct dhcp6_timer *dhcp6_add_timer(struct dhcp6_timer *(*)(void *),
+					 void *);
+void dhcp6_set_timer(struct timeval *, struct dhcp6_timer *);
+void dhcp6_remove_timer(struct dhcp6_timer **);
+struct timeval *dhcp6_check_timer(void);
+struct timeval *dhcp6_timer_rest(struct dhcp6_timer *);
 
-void timeval_sub __P((struct timeval *, struct timeval *,
-			     struct timeval *));
+void timeval_sub(struct timeval *, struct timeval *, struct timeval *);
+
+#endif
