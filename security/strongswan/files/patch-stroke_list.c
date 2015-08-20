--- src/libcharon/plugins/stroke/stroke_list.c.orig	2014-09-10 21:55:24.000000000 +0200
+++ src/libcharon/plugins/stroke/stroke_list.c	2014-09-10 21:55:33.000000000 +0200
@@ -1521,23 +1521,31 @@
 	bool on;
 	int found = 0;
 
+#if 0
 	fprintf(out, "Leases in pool '%s', usage: %u/%u, %u online\n",
 			pool, online + offline, size, online);
+#endif
+	fprintf(out, "<pool>\n<name>%s</name><size>%u</size><usage>%u</usage><online>%u</online>\n",
+			pool, size, online + offline, online);
 	enumerator = this->attribute->create_lease_enumerator(this->attribute, pool);
 	while (enumerator && enumerator->enumerate(enumerator, &id, &lease, &on))
 	{
 		if (!address || address->ip_equals(address, lease))
 		{
-			fprintf(out, "  %15H   %s   '%Y'\n",
+			fprintf(out, "<lease>\n<host>%H</host><status>%s</status><id>%Y</id>\n</lease>\n",
+			//fprintf(out, "  %15H   %s   '%Y'\n",
 					lease, on ? "online" : "offline", id);
 			found++;
 		}
 	}
 	enumerator->destroy(enumerator);
+	fprintf(out, "</pool>\n");
+#if 0
 	if (!found)
 	{
 		fprintf(out, "  no matching leases found\n");
 	}
+#endif
 }
 
 METHOD(stroke_list_t, leases, void,
@@ -1554,6 +1562,7 @@
 		address = host_create_from_string(msg->leases.address, 0);
 	}
 
+	fprintf(out, "<leases>\n");
 	enumerator = this->attribute->create_pool_enumerator(this->attribute);
 	while (enumerator->enumerate(enumerator, &pool, &size, &online, &offline))
 	{
@@ -1564,6 +1573,8 @@
 		}
 	}
 	enumerator->destroy(enumerator);
+	fprintf(out, "</leases>\n");
+#if 0
 	if (!found)
 	{
 		if (msg->leases.pool)
@@ -1575,6 +1586,7 @@
 			fprintf(out, "no pools found\n");
 		}
 	}
+#endif
 	DESTROY_IF(address);
 }
 
