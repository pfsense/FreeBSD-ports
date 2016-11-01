--- drivers/net/phy/micrel.c.orig	2016-03-14 14:20:21 UTC
+++ drivers/net/phy/micrel.c
@@ -410,6 +410,33 @@ static int ksz9031_of_config(struct phy_
 
 	return 0;
 }
+#elif defined(CONFIG_UBMC) || defined(CONFIG_UFW)
+static int ksz9031_of_config(struct phy_device *phydev)
+{
+	int err, val;
+	struct phy_driver *drv = phydev->drv;
+
+	if (!drv || !drv->writeext)
+		return -EOPNOTSUPP;
+
+	err = drv->writeext(phydev, 0, 2,
+	    MII_KSZ9031_EXT_RGMII_CTRL_SIG_SKEW, 0x70);
+	if (err != 0)
+		return (err);
+
+	err = drv->writeext(phydev, 0, 2,
+	    MII_KSZ9031_EXT_RGMII_RX_DATA_SKEW, 0x7777);
+	if (err != 0)
+		return (err);
+
+	err = drv->writeext(phydev, 0, 2,
+	    MII_KSZ9031_EXT_RGMII_TX_DATA_SKEW, 0);
+	if (err != 0)
+		return (err);
+
+	return drv->writeext(phydev, 0, 2,
+	    MII_KSZ9031_EXT_RGMII_CLOCK_SKEW, 0x3f6);
+}
 #else
 static int ksz9031_of_config(struct phy_device *phydev)
 {
