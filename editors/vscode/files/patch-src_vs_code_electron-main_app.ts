--- src/vs/code/electron-main/app.ts.orig	2026-03-17 18:09:23 UTC
+++ src/vs/code/electron-main/app.ts
@@ -1028,6 +1028,7 @@ export class CodeApplication extends Disposable {
 				break;
 
 			case 'linux':
+			case 'freebsd':
 				if (isLinuxSnap) {
 					services.set(IUpdateService, new SyncDescriptor(SnapUpdateService, [process.env['SNAP'], process.env['SNAP_REVISION']]));
 				} else {
