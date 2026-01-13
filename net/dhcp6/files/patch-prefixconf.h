--- prefixconf.h.orig	2017-02-28 19:06:15 UTC
+++ prefixconf.h
@@ -28,14 +28,18 @@
  * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
  * SUCH DAMAGE.
  */
+#ifndef	_PREFIXCONF_H_
+#define	_PREFIXCONF_H_
 
 typedef enum { PREFIX6S_ACTIVE, PREFIX6S_RENEW,
 	       PREFIX6S_REBIND} prefix6state_t;
 
-extern int update_prefix __P((struct ia *, struct dhcp6_prefix *,
+int update_prefix(struct ia *, struct dhcp6_prefix *,
     struct pifc_list *, struct dhcp6_if *, struct iactl **,
-    void (*)__P((struct ia *))));
-extern int prefix6_add __P((struct dhcp6_if *, struct dhcp6_prefix *,
-			       struct duid *));
-extern int prefix6_update __P((struct dhcp6_event *, struct dhcp6_list *,
-				  struct duid *));
+    void (*)(struct ia *));
+int prefix6_add(struct dhcp6_if *, struct dhcp6_prefix *,
+			       struct duid *);
+int prefix6_update(struct dhcp6_event *, struct dhcp6_list *,
+				  struct duid *);
+
+#endif
