--- drivers/mmc/mmc.c.orig	2016-03-14 14:20:21 UTC
+++ drivers/mmc/mmc.c
@@ -23,6 +23,7 @@
 
 static struct list_head mmc_devices;
 static int cur_dev_num = -1;
+static int mmc_error_print_max = -1;
 
 __weak int board_mmc_getwp(struct mmc *mmc)
 {
@@ -1582,8 +1583,13 @@ void mmc_destroy(struct mmc *mmc)
 block_dev_desc_t *mmc_get_dev(int dev)
 {
 	struct mmc *mmc = find_mmc_device(dev);
-	if (!mmc || mmc_init(mmc))
+	if (!mmc)
 		return NULL;
+	/*
+	 * If mmc_init fails, mmc->block_dev will be of type
+	 * DEV_TYPE_UNKNOWN with blksz and lba set to zero.
+	 */
+ 	mmc_init(mmc);
 
 	return &mmc->block_dev;
 }
@@ -1619,7 +1625,7 @@ int mmc_start_init(struct mmc *mmc)
 	err = mmc->cfg->ops->init(mmc);
 
 	if (err)
-		return err;
+		goto done;
 
 	mmc->ddr_mode = 0;
 	mmc_set_bus_width(mmc, 1);
@@ -1629,7 +1635,7 @@ int mmc_start_init(struct mmc *mmc)
 	err = mmc_go_idle(mmc);
 
 	if (err)
-		return err;
+		goto done;
 
 	/* The internal partition reset to user partition(0) at every CMD0*/
 	mmc->block_dev.hwpart = 0;
@@ -1646,15 +1652,30 @@ int mmc_start_init(struct mmc *mmc)
 
 		if (err) {
 #if !defined(CONFIG_SPL_BUILD) || defined(CONFIG_SPL_LIBCOMMON_SUPPORT)
-			printf("Card did not respond to voltage select!\n");
+			if (mmc_error_print_max < 4) {
+				mmc_error_print_max++;
+				printf("Card did not respond to voltage select!\n");
+				if (mmc_error_print_max == 4)
+					printf("Discarding further error messages\n");
+			}
 #endif
-			return UNUSABLE_ERR;
+			goto done;
 		}
 	}
 
 	if (!err)
 		mmc->init_in_progress = 1;
 
+done:
+	if (err) {
+		mmc->has_init = 0;
+		mmc->block_dev.type = DEV_TYPE_UNKNOWN;
+		mmc->block_dev.blksz = 0;
+		mmc->block_dev.lba = 0;
+	} else {
+		mmc->has_init = 1;
+	}
+
 	return err;
 }
 
