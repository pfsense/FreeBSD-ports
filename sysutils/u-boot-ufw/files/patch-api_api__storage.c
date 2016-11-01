--- api/api_storage.c.orig	2016-03-14 14:20:21 UTC
+++ api/api_storage.c
@@ -107,10 +107,13 @@ static int dev_stor_get(int type, int fi
 
 	if (first) {
 		di->cookie = (void *)get_dev(specs[type].name, 0);
-		if (di->cookie == NULL)
+		if (di->cookie == NULL) {
 			return 0;
-		else
+		} else {
 			found = 1;
+			if (specs[type].max_dev > 1)
+				*more = 1;
+		}
 
 		/* provide hint if there are more devices in
 		 * this group to enumerate */
@@ -151,7 +154,8 @@ static int dev_stor_get(int type, int fi
 			dd = (block_dev_desc_t *)di->cookie;
 			if (dd->type == DEV_TYPE_UNKNOWN) {
 				debugf("device instance exists, but is not active..");
-				found = 0;
+				di->di_stor.block_count = 0;
+				di->di_stor.block_size = 0;
 			} else {
 				di->di_stor.block_count = dd->lba;
 				di->di_stor.block_size = dd->blksz;
