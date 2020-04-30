--- src/vs/workbench/contrib/extensions/browser/extensionEditor.ts.orig	2020-03-09 16:22:02 UTC
+++ src/vs/workbench/contrib/extensions/browser/extensionEditor.ts
@@ -1361,7 +1361,8 @@ export class ExtensionEditor extends BaseEditor {
 
 		switch (platform) {
 			case 'win32': key = rawKeyBinding.win; break;
-			case 'linux': key = rawKeyBinding.linux; break;
+			case 'linux': case 'freebsd':
+				key = rawKeyBinding.linux; break;
 			case 'darwin': key = rawKeyBinding.mac; break;
 		}
 
