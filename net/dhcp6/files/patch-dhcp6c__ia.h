--- dhcp6c_ia.h.orig	2017-02-28 19:06:15 UTC
+++ dhcp6c_ia.h
@@ -28,6 +28,8 @@
  * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
  * SUCH DAMAGE.
  */
+#ifndef	_DHCP6C_IA_H_
+#define	_DHCP6C_IA_H_
 
 struct ia;			/* this is an opaque type */
 
@@ -35,22 +37,24 @@ struct iactl {
 	struct ia *iactl_ia;	/* back pointer to IA */
 
 	/* callback function called when something may happen on the IA */
-	void (*callback) __P((struct ia *));
+	void (*callback)(struct ia *);
 
 	/* common methods: */
-	int (*isvalid) __P((struct iactl *));
-	u_int32_t (*duration) __P((struct iactl *));
-	int (*renew_data) __P((struct iactl *, struct dhcp6_ia *,
-	    struct dhcp6_eventdata **, struct dhcp6_eventdata *));
-	int (*rebind_data) __P((struct iactl *, struct dhcp6_ia *,
-	    struct dhcp6_eventdata **, struct dhcp6_eventdata *));
-	int (*release_data) __P((struct iactl *, struct dhcp6_ia *,
-	    struct dhcp6_eventdata **, struct dhcp6_eventdata *));
-	int (*reestablish_data) __P((struct iactl *, struct dhcp6_ia *,
-	    struct dhcp6_eventdata **, struct dhcp6_eventdata *));
-	void (*cleanup) __P((struct iactl *));
+	int (*isvalid)(struct iactl *);
+	uint32_t (*duration)(struct iactl *);
+	int (*renew_data)(struct iactl *, struct dhcp6_ia *,
+	    struct dhcp6_eventdata **, struct dhcp6_eventdata *);
+	int (*rebind_data)(struct iactl *, struct dhcp6_ia *,
+	    struct dhcp6_eventdata **, struct dhcp6_eventdata *);
+	int (*release_data)(struct iactl *, struct dhcp6_ia *,
+	    struct dhcp6_eventdata **, struct dhcp6_eventdata *);
+	int (*reestablish_data)(struct iactl *, struct dhcp6_ia *,
+	    struct dhcp6_eventdata **, struct dhcp6_eventdata *);
+	void (*cleanup)(struct iactl *);
 };
 
-extern void update_ia __P((iatype_t, struct dhcp6_list *,
-    struct dhcp6_if *, struct duid *, struct authparam *));
-extern void release_all_ia __P((struct dhcp6_if *));
+void update_ia(iatype_t, struct dhcp6_list *,
+    struct dhcp6_if *, struct duid *, struct authparam *);
+void release_all_ia(struct dhcp6_if *);
+
+#endif
