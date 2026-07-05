--- zebra/kernel_socket.c.orig	2024-08-15 18:44:19 UTC
+++ zebra/kernel_socket.c
@@ -1470,10 +1470,12 @@ void interface_list_second(struct zebra_ns *zns)
 
 void interface_list_second(struct zebra_ns *zns)
 {
+	zebra_dplane_startup_stage(zns, ZEBRA_DPLANE_ADDRESSES_READ);
 }
 
 void interface_list_tunneldump(struct zebra_ns *zns)
 {
+	zebra_dplane_startup_stage(zns, ZEBRA_DPLANE_TUNNELS_READ);
 }
 
 /* Exported interface function.  This function simply calls
