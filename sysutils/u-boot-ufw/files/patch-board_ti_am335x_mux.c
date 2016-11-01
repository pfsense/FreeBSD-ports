--- board/ti/am335x/mux.c.orig	2016-03-14 14:20:21 UTC
+++ board/ti/am335x/mux.c
@@ -103,6 +103,21 @@ static struct module_pin_mux mmc1_pin_mu
 	{-1},
 };
 
+static struct module_pin_mux mmc1_pin_mux_ubmc[] = {
+	{OFFSET(gpmc_ad7), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT7 */
+	{OFFSET(gpmc_ad6), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT6 */
+	{OFFSET(gpmc_ad5), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT5 */
+	{OFFSET(gpmc_ad4), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT4 */
+	{OFFSET(gpmc_ad3), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT3 */
+	{OFFSET(gpmc_ad2), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT2 */
+	{OFFSET(gpmc_ad1), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT1 */
+	{OFFSET(gpmc_ad0), (MODE(1) | RXACTIVE | PULLUP_EN)},	/* MMC1_DAT0 */
+	{OFFSET(gpmc_csn1), (MODE(2) | RXACTIVE | PULLUP_EN)},	/* MMC1_CLK */
+	{OFFSET(gpmc_csn2), (MODE(2) | RXACTIVE | PULLUP_EN)},	/* MMC1_CMD */
+	{OFFSET(mcasp0_fsx), (MODE(4) | RXACTIVE | PULLUP_EN)},	/* MMC1_CD */
+	{-1},
+};
+
 static struct module_pin_mux i2c0_pin_mux[] = {
 	{OFFSET(i2c0_sda), (MODE(0) | RXACTIVE |
 			PULLUDEN | SLEWCTRL)}, /* I2C_DATA */
@@ -134,6 +149,24 @@ static struct module_pin_mux gpio0_7_pin
 	{-1},
 };
 
+static struct module_pin_mux rgmii2_pin_mux[] = {
+	{OFFSET(gpmc_a0), MODE(2)},			/* RGMII2_TCTL */
+	{OFFSET(gpmc_a1), MODE(2) | RXACTIVE},		/* RGMII2_RCTL */
+	{OFFSET(gpmc_a2), MODE(2)},			/* RGMII2_TD3 */
+	{OFFSET(gpmc_a3), MODE(2)},			/* RGMII2_TD2 */
+	{OFFSET(gpmc_a4), MODE(2)},			/* RGMII2_TD1 */
+	{OFFSET(gpmc_a5), MODE(2)},			/* RGMII2_TD0 */
+	{OFFSET(gpmc_a6), MODE(2)},			/* RGMII2_TCLK */
+	{OFFSET(gpmc_a7), MODE(2) | RXACTIVE},		/* RGMII2_RCLK */
+	{OFFSET(gpmc_a8), MODE(2) | RXACTIVE},		/* RGMII2_RD3 */
+	{OFFSET(gpmc_a9), MODE(2) | RXACTIVE},		/* RGMII2_RD2 */
+	{OFFSET(gpmc_a10), MODE(2) | RXACTIVE},		/* RGMII2_RD1 */
+	{OFFSET(gpmc_a11), MODE(2) | RXACTIVE},		/* RGMII2_RD0 */
+	{OFFSET(mdio_data), MODE(0) | RXACTIVE | PULLUP_EN},/* MDIO_DATA */
+	{OFFSET(mdio_clk), MODE(0) | PULLUP_EN},	/* MDIO_CLK */
+	{-1},
+};
+
 static struct module_pin_mux rgmii1_pin_mux[] = {
 	{OFFSET(mii1_txen), MODE(2)},			/* RGMII1_TCTL */
 	{OFFSET(mii1_rxdv), MODE(2) | RXACTIVE},	/* RGMII1_RCTL */
@@ -315,7 +348,13 @@ static unsigned short detect_daughter_bo
 void enable_board_pin_mux(struct am335x_baseboard_id *header)
 {
 	/* Do board-specific muxes. */
-	if (board_is_bone(header)) {
+	if (board_is_ubmc(header)) {
+		configure_module_pin_mux(rgmii1_pin_mux);
+		configure_module_pin_mux(rgmii2_pin_mux);
+		if (board_is_ufw(header))
+			configure_module_pin_mux(mmc0_pin_mux_sk_evm);
+		configure_module_pin_mux(mmc1_pin_mux_ubmc);
+	} else if (board_is_bone(header)) {
 		/* Beaglebone pinmux */
 		configure_module_pin_mux(mii1_pin_mux);
 		configure_module_pin_mux(mmc0_pin_mux);
