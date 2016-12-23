--- src/igmpproxy.c.orig
+++ src/igmpproxy.c
@@ -186,8 +186,10 @@ int igmpProxyInit() {
                     }
                 }
 
-                addVIF( Dp );
-                vifcount++;
+                if (Dp->state != IF_STATE_DISABLED) {
+                    addVIF( Dp );
+                    vifcount++;
+                }
             }
         }
 
