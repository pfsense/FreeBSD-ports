--- electron/spec/api-process-spec.js.orig	2021-01-22 23:55:24 UTC
+++ electron/spec/api-process-spec.js
@@ -50,7 +50,7 @@ describe('process module', () => {
     it('resolves promise successfully with valid data', async () => {
       const memoryInfo = await process.getProcessMemoryInfo();
       expect(memoryInfo).to.be.an('object');
-      if (process.platform === 'linux' || process.platform === 'windows') {
+      if (process.platform === 'linux' || process.platform === 'windows' || process.platform === 'freebsd') {
         expect(memoryInfo.residentSet).to.be.a('number').greaterThan(0);
       }
       expect(memoryInfo.private).to.be.a('number').greaterThan(0);
