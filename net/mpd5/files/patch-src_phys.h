--- src/phys.h	2013-06-11 10:00:00.000000000 +0100
+++ src/phys.h	2015-09-22 20:49:38.000000000 +0100
@@ -64,6 +64,8 @@
 						/* returns the calling number (IP, MAC, whatever) */
     int		(*callednum)(Link l, void *buf, size_t buf_len); 
 						/* returns the called number (IP, MAC, whatever) */
+    u_short	(*getmtu)(Link l, int conf);	/* returns actual MTU */
+    u_short	(*getmru)(Link l, int conf);	/* returns actual MRU */
   };
   typedef struct phystype	*PhysType;
 
@@ -99,6 +101,8 @@
   extern int		PhysGetPeerIface(Link l, char *buf, size_t buf_len);
   extern int		PhysGetCallingNum(Link l, char *buf, size_t buf_len);
   extern int		PhysGetCalledNum(Link l, char *buf, size_t buf_len);
+  extern u_short	PhysGetMtu(Link l, int conf);
+  extern u_short	PhysGetMru(Link l, int conf);
   extern int		PhysIsBusy(Link l);
  
   extern int		PhysInit(Link l);
