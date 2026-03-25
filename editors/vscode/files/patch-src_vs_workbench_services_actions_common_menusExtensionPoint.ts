--- src/vs/workbench/services/actions/common/menusExtensionPoint.ts.orig	2026-03-17 18:09:23 UTC
+++ src/vs/workbench/services/actions/common/menusExtensionPoint.ts
@@ -1233,7 +1233,10 @@ class CommandsTableRenderer extends Disposable impleme
 
 		switch (platform) {
 			case 'win32': key = rawKeyBinding.win; break;
-			case 'linux': key = rawKeyBinding.linux; break;
+			case 'linux':
+			case 'freebsd':
+				key = rawKeyBinding.linux;
+				break;
 			case 'darwin': key = rawKeyBinding.mac; break;
 		}
 
