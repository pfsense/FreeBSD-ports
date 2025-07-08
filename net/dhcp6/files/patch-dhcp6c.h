--- dhcp6c.h.orig	2017-02-28 19:06:15 UTC
+++ dhcp6c.h
@@ -28,10 +28,17 @@
  * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
  * SUCH DAMAGE.
  */
+#ifndef	_DHCP6C_H_
+#define	_DHCP6C_H_
+
 #define DHCP6C_CONF SYSCONFDIR "/dhcp6c.conf"
 #define DHCP6C_PIDFILE "/var/run/dhcp6c.pid"
 #define DUID_FILE LOCALDBDIR "/dhcp6c_duid"
 
-extern struct dhcp6_timer *client6_timo __P((void *));
-extern int client6_start __P((struct dhcp6_if *));
-extern void client6_send __P((struct dhcp6_event *));
+struct dhcp6_timer *client6_timo(void *);
+int client6_start(struct dhcp6_if *);
+void client6_send(struct dhcp6_event *);
+
+int client6_script(char *, int, struct dhcp6_optinfo *, struct dhcp6_if *);
+
+#endif
