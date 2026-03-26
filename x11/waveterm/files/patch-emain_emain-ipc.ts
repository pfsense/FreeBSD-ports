--- emain/emain-ipc.ts.orig	2026-03-12 23:29:48 UTC
+++ emain/emain-ipc.ts
@@ -354,7 +354,7 @@ export function initIpcHandlers() {
             const color = fac.prepareResult(fac.getColorFromArray4(png.data));
             const ww = getWaveWindowByWebContentsId(event.sender.id);
             ww.setTitleBarOverlay({
-                color: unamePlatform === "linux" ? color.rgba : "#00000000",
+                color: unamePlatform === "linux" || unamePlatform === "freebsd" ? color.rgba : "#00000000",
                 symbolColor: color.isDark ? "white" : "black",
             });
         } catch (e) {
