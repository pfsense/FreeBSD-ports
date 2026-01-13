--- timer.c.orig	2017-02-28 19:06:15 UTC
+++ timer.c
@@ -49,12 +49,12 @@
 
 #define MILLION 1000000
 
-LIST_HEAD(, dhcp6_timer) timer_head;
+static LIST_HEAD(, dhcp6_timer) timer_head;
 static struct timeval tm_sentinel;
 static struct timeval tm_max = {0x7fffffff, 0x7fffffff};
 
-static void timeval_add __P((struct timeval *, struct timeval *,
-			     struct timeval *));
+static void timeval_add(struct timeval *, struct timeval *,
+			     struct timeval *);
 
 void
 dhcp6_timer_init()
@@ -65,7 +65,7 @@ dhcp6_timer_init()
 
 struct dhcp6_timer *
 dhcp6_add_timer(timeout, timeodata)
-	struct dhcp6_timer *(*timeout) __P((void *));
+	struct dhcp6_timer *(*timeout)(void *);
 	void *timeodata;
 {
 	struct dhcp6_timer *newtimer;
