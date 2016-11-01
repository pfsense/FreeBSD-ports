--- arch/arm/cpu/armv7/am33xx/board.c.orig	2016-03-14 14:20:21 UTC
+++ arch/arm/cpu/armv7/am33xx/board.c
@@ -108,11 +108,13 @@ const struct gpio_bank *const omap_gpio_
 #if defined(CONFIG_OMAP_HSMMC) && !defined(CONFIG_SPL_BUILD)
 int cpu_mmc_init(bd_t *bis)
 {
+#ifndef CONFIG_UBMC
 	int ret;
 
 	ret = omap_mmc_init(0, 0, 0, -1, -1);
 	if (ret)
 		return ret;
+#endif
 
 	return omap_mmc_init(1, 0, 0, -1, -1);
 }
