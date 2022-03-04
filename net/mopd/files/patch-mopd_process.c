--- mopd/process.c.orig	1996-08-22 17:07:38 UTC
+++ mopd/process.c
@@ -265,7 +265,7 @@ mopStartLoad(dst, src, dl_rpr, trans)
 	dllist[slot].a_lseek   = 0;
 
 	dllist[slot].count     = 0;
-	if (dllist[slot].dl_bsz >= 1492)
+	if ((dllist[slot].dl_bsz >= 1492) || (dllist[slot].dl_bsz == 0))
 		dllist[slot].dl_bsz = 1492;
 	if (dllist[slot].dl_bsz == 1030)	/* VS/uVAX 2000 needs this */
 		dllist[slot].dl_bsz = 1000;
@@ -348,10 +348,10 @@ mopNextLoad(dst, src, new_count, trans)
 		close(dllist[slot].ldfd);
 		dllist[slot].ldfd = 0;
 		dllist[slot].status = DL_STATUS_FREE;
-		sprintf(line,
+		snprintf(line,sizeof(line),
 			"%x:%x:%x:%x:%x:%x Load completed",
 			dst[0],dst[1],dst[2],dst[3],dst[4],dst[5]);
-		syslog(LOG_INFO, line);
+		syslog(LOG_INFO, "%s", line);
 		return;
 	}
 
@@ -436,7 +436,7 @@ mopProcessDL(fd, ii, pkt, index, dst, src, trans, len)
 {
 	u_char  tmpc;
 	u_short moplen;
-	u_char  pfile[17], mopcode;
+	u_char  pfile[129], mopcode;
 	char    filename[FILENAME_MAX];
 	char    line[100];
 	int     i,nfd,iindex;
@@ -485,6 +485,8 @@ mopProcessDL(fd, ii, pkt, index, dst, src, trans, len)
 		rpr_pgty = mopGetChar(pkt,index);	/* Program Type */
 		
 		tmpc = mopGetChar(pkt,index);		/* Software ID Len */
+		if (tmpc > sizeof(pfile) - 1)
+			return;
 		for (i = 0; i < tmpc; i++) {
 			pfile[i] = mopGetChar(pkt,index);
 			pfile[i+1] = '\0';
@@ -511,31 +513,32 @@ mopProcessDL(fd, ii, pkt, index, dst, src, trans, len)
 		bcopy((char *)src, (char *)(dl_rpr->eaddr), 6);
 		mopProcessInfo(pkt,index,moplen,dl_rpr,trans);
 
-		sprintf(filename,"%s/%s.SYS", MOP_FILE_PATH, pfile);
+		snprintf(filename,sizeof(filename),
+			"%s/%s.SYS", MOP_FILE_PATH, pfile);
 		if ((mopCmpEAddr(dst,dl_mcst) == 0)) {
 			if ((nfd = open(filename, O_RDONLY, 0)) != -1) {
 				close(nfd);
 				mopSendASV(src, ii->eaddr, ii, trans);
-				sprintf(line,
+				snprintf(line,sizeof(line),
 					"%x:%x:%x:%x:%x:%x (%d) Do you have %s? (Yes)",
 					src[0],src[1],src[2],
 					src[3],src[4],src[5],trans,pfile);
 			} else {
-				sprintf(line,
+				snprintf(line,sizeof(line),
 					"%x:%x:%x:%x:%x:%x (%d) Do you have %s? (No)",
 					src[0],src[1],src[2],
 					src[3],src[4],src[5],trans,pfile);
 			}
-			syslog(LOG_INFO, line);
+			syslog(LOG_INFO, "%s", line);
 		} else {
 			if ((mopCmpEAddr(dst,ii->eaddr) == 0)) {
 				dl_rpr->ldfd = open(filename, O_RDONLY, 0);
 				mopStartLoad(src, ii->eaddr, dl_rpr, trans);
-				sprintf(line,
+				snprintf(line,sizeof(line),
 					"%x:%x:%x:%x:%x:%x Send me %s",
 					src[0],src[1],src[2],
 					src[3],src[4],src[5],pfile);
-				syslog(LOG_INFO, line);
+				syslog(LOG_INFO, "%s", line);
 			}
 		}
 		
