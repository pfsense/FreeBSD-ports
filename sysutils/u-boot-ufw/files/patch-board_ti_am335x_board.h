--- board/ti/am335x/board.h.orig	2016-03-14 14:20:21 UTC
+++ board/ti/am335x/board.h
@@ -29,6 +29,17 @@ struct am335x_baseboard_id {
 	char mac_addr[HDR_NO_OF_MAC_ADDR][HDR_ETH_ALEN];
 };
 
+static inline int board_is_ufw(struct am335x_baseboard_id *header)
+{
+	return !strncmp(header->name, "A335uFW", HDR_NAME_LEN);
+}
+
+static inline int board_is_ubmc(struct am335x_baseboard_id *header)
+{
+	return (!strncmp(header->name, "A335uBMC", HDR_NAME_LEN) ||
+	    !strncmp(header->name, "A335uFW", HDR_NAME_LEN));
+}
+
 static inline int board_is_bone(struct am335x_baseboard_id *header)
 {
 	return !strncmp(header->name, "A335BONE", HDR_NAME_LEN);
