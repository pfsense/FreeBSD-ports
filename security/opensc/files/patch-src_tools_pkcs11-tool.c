--- src/tools/pkcs11-tool.c.orig	2023-02-24 15:56:57 UTC
+++ src/tools/pkcs11-tool.c
@@ -7347,6 +7347,8 @@ static int test_random(CK_SESSION_HANDLE session)
 		errors++;
 	}
 
+	(void) errors; /* make compiler happy */
+
 	printf("  seems to be OK\n");
 
 	return 0;
