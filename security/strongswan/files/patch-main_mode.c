--- src/libcharon/sa/ikev1/tasks/main_mode.c.orig	2015-01-14 11:38:41.000000000 +0100
+++ src/libcharon/sa/ikev1/tasks/main_mode.c	2015-01-14 11:43:18.000000000 +0100
@@ -337,7 +337,7 @@
 				return send_notify(this, AUTHENTICATION_FAILED);
 			}
 
-			add_initial_contact(this, message, id);
+			//add_initial_contact(this, message, id);
 
 			this->state = MM_AUTH;
 			return NEED_MORE;
