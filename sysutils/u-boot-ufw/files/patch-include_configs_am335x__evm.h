--- include/configs/am335x_evm.h.orig	2016-03-14 14:20:21 UTC
+++ include/configs/am335x_evm.h
@@ -315,9 +315,7 @@
 #define CONFIG_USB_GADGET_VBUS_DRAW	2
 #define CONFIG_USB_MUSB_HOST
 #define CONFIG_AM335X_USB0
-#define CONFIG_AM335X_USB0_MODE	MUSB_PERIPHERAL
-#define CONFIG_AM335X_USB1
-#define CONFIG_AM335X_USB1_MODE MUSB_HOST
+#define CONFIG_AM335X_USB0_MODE	MUSB_HOST
 
 #ifndef CONFIG_SPL_USBETH_SUPPORT
 /* Fastboot */
@@ -445,8 +443,8 @@
 #define CONFIG_SYS_REDUNDAND_ENVIRONMENT
 #define CONFIG_ENV_SPI_MAX_HZ		CONFIG_SF_DEFAULT_SPEED
 #define CONFIG_ENV_SECT_SIZE		(4 << 10) /* 4 KB sectors */
-#define CONFIG_ENV_OFFSET		(768 << 10) /* 768 KiB in */
-#define CONFIG_ENV_OFFSET_REDUND	(896 << 10) /* 896 KiB in */
+#define CONFIG_ENV_OFFSET		(640 << 10) /* 640 KiB in */
+#define CONFIG_ENV_OFFSET_REDUND	(768 << 10) /* 768 KiB in */
 #define MTDIDS_DEFAULT			"nor0=m25p80-flash.0"
 #define MTDPARTS_DEFAULT		"mtdparts=m25p80-flash.0:128k(SPL)," \
 					"512k(u-boot),128k(u-boot-env1)," \
@@ -512,4 +510,117 @@
 #endif
 #endif  /* NOR support */
 
+/*****************************************************************************
+ * uBMC and uFW customizations from here down.
+ ****************************************************************************/
+
+#define CONFIG_SYS_TIMERBASE		0x48040000	/* Use Timer2 */
+#define CONFIG_SYS_PTV			2	/* Divisor: 2^(PTV+1) => 8 */
+#define CONFIG_SYS_HZ			1000
+#define CONFIG_BAUDRATE			115200
+#define	CONFIG_SERIAL1			1
+#define	CONFIG_CONS_INDEX		1
+#define	CONFIG_SYS_CONSOLE_INFO_QUIET
+#define CONFIG_PHY_GIGE
+#define CONFIG_PHYLIB
+#define CONFIG_PHY_MICREL
+#define CONFIG_PHY_MICREL_KSZ9031
+
+/* SPL */
+#ifndef CONFIG_NOR_BOOT
+#define	CONFIG_SPL_SERIAL_SUPPORT
+#define CONFIG_SPL_BOARD_INIT
+#define	BOOT_DEVICE_QSPI_4		0x0B
+#endif
+
+/* Add the API and ELF features needed for ubldr. */
+#ifndef CONFIG_SPL_BUILD
+#define CONFIG_API
+#define CONFIG_EFI_PARTITION
+#define CONFIG_SYS_MMC_MAX_DEVICE 2
+#ifndef CONFIG_SYS_DCACHE_OFF
+#define CONFIG_CMD_CACHE
+#endif
+#endif
+
+#ifdef CONFIG_UBMC
+#define CONFIG_CMD_SPI
+#endif
+
+#ifdef CONFIG_UFW
+/* Save the env to the fat partition. */
+#ifndef CONFIG_SPL_BUILD
+#undef  CONFIG_ENV_IS_NOWHERE
+#undef  CONFIG_ENV_IS_IN_NAND
+#undef  CONFIG_ENV_IS_IN_MMC
+#define CONFIG_ENV_IS_IN_FAT
+#define CONFIG_FAT_WRITE
+#define FAT_ENV_INTERFACE	"mmc"
+#define FAT_ENV_DEVICE_AND_PART	"0"
+#define FAT_ENV_FILE		"u-boot.env"
+#define MTDPARTS_DEFAULT	"mtdparts="
+#endif
+#endif
+
+/* Create a small(ish) boot environment for FreeBSD. */
+#ifndef CONFIG_SPL_BUILD
+#undef  CONFIG_EXTRA_ENV_SETTINGS
+#define CONFIG_EXTRA_ENV_SETTINGS \
+	MTDPARTS_DEFAULT \
+	"\0" \
+	"loadaddr=88000000\0" \
+	"Netboot=" \
+	  "env exists loaderdev || env set loaderdev net; " \
+	  "env exists UserNetboot && run UserNetboot; " \
+	  "echo Booting from: DHCP; " \
+	  "dhcp ${loadaddr} ${bootfile} && go ${loadaddr}; " \
+	"\0" \
+	"SetupFdtfile=" \
+	  "if test ${board_name} = A335uBMC; then " \
+	      "env set fdt_file ubmc.dtb; " \
+	  "elif test ${board_name} = A335uFW; then " \
+	      "env set fdt_file ufw.dtb; " \
+	  "fi; " \
+	"\0" \
+	"SetupSpiboot=" \
+	  "env set ubldroff 0xe0000;" \
+	  "env set ubldrsize 0x80000;" \
+	"\0" \
+	"SetupFatdev=" \
+	  "env exists fatdev || " \
+	    "usb start && fatdev='usb 0'; fatsize ${fatdev} ${bootfile} || " \
+	    "fatdev='mmc 0'; fatsize ${fatdev} ${bootfile} || " \
+	    "fatdev='mmc 1'; fatsize ${fatdev} ${bootfile} || " \
+	    "env set trynetboot 1; " \
+	"\0" \
+	"Preboot=" \
+	  "env exists bootfile || bootfile=ubldr.bin; " \
+	  "env exists SetupFdtfile && run SetupFdtfile; " \
+	  "env exists SetupSpiboot && run SetupSpiboot; " \
+	  "env exists SetupFatdev && run SetupFatdev; " \
+	  "env exists UserPreboot && run UserPreboot; " \
+	"\0" \
+	"Spiboot=" \
+	  "sf probe; sf read ${loadaddr} ${ubldroff} ${ubldrsize}; " \
+	  "go ${loadaddr}; " \
+	"\0" \
+	"Fatboot=" \
+	  "env exists loaderdev || env set loaderdev ${fatdev}; " \
+	  "env exists UserFatboot && run UserFatboot; " \
+	  "env exists trynetboot && env exists Netboot && run Netboot; " \
+	  "echo Booting from: ${fatdev} ${bootfile}; " \
+	  "fatload ${fatdev} ${loadaddr} ${bootfile} && go ${loadaddr}; " \
+	"\0"
+
+#undef  CONFIG_BOOTCOMMAND
+#ifdef CONFIG_UBMC
+#define CONFIG_BOOTCOMMAND	"run Spiboot"
+#endif
+#ifdef CONFIG_UFW
+#define CONFIG_BOOTCOMMAND	"run Fatboot"
+#endif
+#undef  CONFIG_PREBOOT
+#define CONFIG_PREBOOT		"run Preboot"
+#endif
+
 #endif	/* ! __CONFIG_AM335X_EVM_H */
