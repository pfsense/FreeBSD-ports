--- e1000_ich8lan.c.orig	2024-11-26 13:28:34 UTC
+++ e1000_ich8lan.c
@@ -1502,24 +1502,24 @@ s32 e1000_disable_ulp_lpt_lp(struct e1000_hw *hw, bool
 	ret_val = e1000_read_phy_reg_hv_locked(hw, I218_ULP_CONFIG1, &phy_reg);
 	if (ret_val)
 		goto release;
-		phy_reg &= ~(I218_ULP_CONFIG1_IND |
-			     I218_ULP_CONFIG1_STICKY_ULP |
-			     I218_ULP_CONFIG1_RESET_TO_SMBUS |
-			     I218_ULP_CONFIG1_WOL_HOST |
-			     I218_ULP_CONFIG1_INBAND_EXIT |
-			     I218_ULP_CONFIG1_EN_ULP_LANPHYPC |
-			     I218_ULP_CONFIG1_DIS_CLR_STICKY_ON_PERST |
-			     I218_ULP_CONFIG1_DISABLE_SMB_PERST);
-		e1000_write_phy_reg_hv_locked(hw, I218_ULP_CONFIG1, phy_reg);
+	phy_reg &= ~(I218_ULP_CONFIG1_IND |
+		     I218_ULP_CONFIG1_STICKY_ULP |
+		     I218_ULP_CONFIG1_RESET_TO_SMBUS |
+		     I218_ULP_CONFIG1_WOL_HOST |
+		     I218_ULP_CONFIG1_INBAND_EXIT |
+		     I218_ULP_CONFIG1_EN_ULP_LANPHYPC |
+		     I218_ULP_CONFIG1_DIS_CLR_STICKY_ON_PERST |
+		     I218_ULP_CONFIG1_DISABLE_SMB_PERST);
+	e1000_write_phy_reg_hv_locked(hw, I218_ULP_CONFIG1, phy_reg);
 
-		/* Commit ULP changes by starting auto ULP configuration */
-		phy_reg |= I218_ULP_CONFIG1_START;
-		e1000_write_phy_reg_hv_locked(hw, I218_ULP_CONFIG1, phy_reg);
+	/* Commit ULP changes by starting auto ULP configuration */
+	phy_reg |= I218_ULP_CONFIG1_START;
+	e1000_write_phy_reg_hv_locked(hw, I218_ULP_CONFIG1, phy_reg);
 
-		/* Clear Disable SMBus Release on PERST# in MAC */
-		mac_reg = E1000_READ_REG(hw, E1000_FEXTNVM7);
-		mac_reg &= ~E1000_FEXTNVM7_DISABLE_SMB_PERST;
-		E1000_WRITE_REG(hw, E1000_FEXTNVM7, mac_reg);
+	/* Clear Disable SMBus Release on PERST# in MAC */
+	mac_reg = E1000_READ_REG(hw, E1000_FEXTNVM7);
+	mac_reg &= ~E1000_FEXTNVM7_DISABLE_SMB_PERST;
+	E1000_WRITE_REG(hw, E1000_FEXTNVM7, mac_reg);
 
 release:
 	hw->phy.ops.release(hw);
@@ -1564,13 +1564,13 @@ static s32 e1000_check_for_copper_link_ich8lan(struct 
 	if (!mac->get_link_status)
 		return E1000_SUCCESS;
 
-		/* First we want to see if the MII Status Register reports
-		 * link.  If so, then we want to get the current speed/duplex
-		 * of the PHY.
-		 */
-		ret_val = e1000_phy_has_link_generic(hw, 1, 0, &link);
-		if (ret_val)
-			return ret_val;
+	/* First we want to see if the MII Status Register reports
+	 * link.  If so, then we want to get the current speed/duplex
+	 * of the PHY.
+	 */
+	ret_val = e1000_phy_has_link_generic(hw, 1, 0, &link);
+	if (ret_val)
+		return ret_val;
 
 	if (hw->mac.type == e1000_pchlan) {
 		ret_val = e1000_k1_gig_workaround_hv(hw, link);
