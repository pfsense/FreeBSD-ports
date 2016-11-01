--- board/ti/am335x/board.c.orig	2016-03-14 14:20:21 UTC
+++ board/ti/am335x/board.c
@@ -43,11 +43,30 @@ DECLARE_GLOBAL_DATA_PTR;
 static struct ctrl_dev *cdev = (struct ctrl_dev *)CTRL_DEVICE_BASE;
 #endif
 
+u32 omap_sys_boot_device(void)
+{
+	return 0x15;
+}
+
 /*
  * Read header information from EEPROM into global structure.
  */
 static int read_eeprom(struct am335x_baseboard_id *header)
 {
+#ifdef CONFIG_UBMC
+	memset(header, 0, sizeof(*header));
+	strncpy(header->name, "A335uBMC", sizeof(header->name));
+	strncpy(header->version, "G00", sizeof(header->version));
+
+	return (0);
+#endif
+#ifdef CONFIG_UFW
+	memset(header, 0, sizeof(*header));
+	strncpy(header->name, "A335uFW", sizeof(header->name));
+	strncpy(header->version, "G00", sizeof(header->version));
+
+	return (0);
+#endif
 	/* Check if baseboard eeprom is available */
 	if (i2c_probe(CONFIG_SYS_I2C_EEPROM_ADDR)) {
 		puts("Could not probe the EEPROM; something fundamentally "
@@ -174,6 +193,17 @@ static struct emif_regs ddr3_emif_reg_da
 				PHY_EN_DYN_PWRDN,
 };
 
+static struct emif_regs ddr3_ubmc_emif_reg_data = {
+	.sdram_config = MT41J128MJT125_EMIF_SDCFG,
+	.ref_ctrl = MT41J128MJT125_EMIF_SDREF,
+	.sdram_tim1 = 0x088ae4db,
+	.sdram_tim2 = 0x24437fda,
+	.sdram_tim3 = 0x501f83ff,
+	.zq_config = MT41J128MJT125_ZQ_CFG,
+	.emif_ddr_phy_ctlr_1 = MT41J128MJT125_EMIF_READ_LATENCY |
+				PHY_EN_DYN_PWRDN,
+};
+
 static struct emif_regs ddr3_beagleblack_emif_reg_data = {
 	.sdram_config = MT41K256M16HA125E_EMIF_SDCFG,
 	.ref_ctrl = MT41K256M16HA125E_EMIF_SDREF,
@@ -232,7 +262,11 @@ void am33xx_spl_board_init(void)
 	/* Get the frequency */
 	dpll_mpu_opp100.m = am335x_get_efuse_mpu_max_freq(cdev);
 
-	if (board_is_bone(&header) || board_is_bone_lt(&header)) {
+	if (board_is_ubmc(&header)) {
+		/* Set CORE Frequencies to OPP100 */
+		do_setup_dpll(&dpll_core_regs, &dpll_core_opp100);
+		dpll_mpu_opp100.m = MPUPLL_M_550;
+	} else if (board_is_bone(&header) || board_is_bone_lt(&header)) {
 		/* BeagleBone PMIC Code */
 		int usb_cur_lim;
 
@@ -374,7 +408,7 @@ const struct dpll_params *get_dpll_ddr_p
 	if (read_eeprom(&header) < 0)
 		puts("Could not get board ID.\n");
 
-	if (board_is_evm_sk(&header))
+	if (board_is_ubmc(&header) || board_is_evm_sk(&header))
 		return &dpll_ddr_evm_sk;
 	else if (board_is_bone_lt(&header))
 		return &dpll_ddr_bone_black;
@@ -459,7 +493,10 @@ void sdram_init(void)
 		gpio_direction_output(GPIO_DDR_VTT_EN, 1);
 	}
 
-	if (board_is_evm_sk(&header))
+	if (board_is_ubmc(&header))
+		config_ddr(303, &ioregs_evmsk, &ddr3_data,
+			   &ddr3_cmd_ctrl_data, &ddr3_ubmc_emif_reg_data, 0);
+	else if (board_is_evm_sk(&header))
 		config_ddr(303, &ioregs_evmsk, &ddr3_data,
 			   &ddr3_cmd_ctrl_data, &ddr3_emif_reg_data, 0);
 	else if (board_is_bone_lt(&header))
@@ -537,12 +574,12 @@ static struct cpsw_slave_data cpsw_slave
 	{
 		.slave_reg_ofs	= 0x208,
 		.sliver_reg_ofs	= 0xd80,
-		.phy_addr	= 0,
+		.phy_addr	= 1,
 	},
 	{
 		.slave_reg_ofs	= 0x308,
 		.sliver_reg_ofs	= 0xdc0,
-		.phy_addr	= 1,
+		.phy_addr	= 2,
 	},
 };
 
@@ -552,8 +589,9 @@ static struct cpsw_platform_data cpsw_da
 	.mdio_div		= 0xff,
 	.channels		= 8,
 	.cpdma_reg_ofs		= 0x800,
-	.slaves			= 1,
+	.slaves			= 2,
 	.slave_data		= cpsw_slaves,
+	.active_slave		= 0,
 	.ale_reg_ofs		= 0xd00,
 	.ale_entries		= 1024,
 	.host_port_reg_ofs	= 0x108,
@@ -590,8 +628,13 @@ int board_eth_init(bd_t *bis)
 	__maybe_unused struct am335x_baseboard_id header;
 
 	/* try reading mac address from efuse */
-	mac_lo = readl(&cdev->macid0l);
-	mac_hi = readl(&cdev->macid0h);
+	if (cpsw_data.active_slave == 0) {
+		mac_lo = readl(&cdev->macid0l);
+		mac_hi = readl(&cdev->macid0h);
+	} else {
+		mac_lo = readl(&cdev->macid1l);
+		mac_hi = readl(&cdev->macid1h);
+	}
 	mac_addr[0] = mac_hi & 0xFF;
 	mac_addr[1] = (mac_hi & 0xFF00) >> 8;
 	mac_addr[2] = (mac_hi & 0xFF0000) >> 16;
@@ -610,8 +653,13 @@ int board_eth_init(bd_t *bis)
 
 #ifdef CONFIG_DRIVER_TI_CPSW
 
-	mac_lo = readl(&cdev->macid1l);
-	mac_hi = readl(&cdev->macid1h);
+	if (cpsw_data.active_slave == 0) {
+		mac_lo = readl(&cdev->macid1l);
+		mac_hi = readl(&cdev->macid1h);
+	} else {
+		mac_lo = readl(&cdev->macid0l);
+		mac_hi = readl(&cdev->macid0h);
+	}
 	mac_addr[0] = mac_hi & 0xFF;
 	mac_addr[1] = (mac_hi & 0xFF00) >> 8;
 	mac_addr[2] = (mac_hi & 0xFF0000) >> 16;
