-- patch out git clone for llama-cpp

--- node_modules/@nanocollective/nanocoder/node_modules/node-llama-cpp/dist/bindings/utils/cloneLlamaCppRepo.js.orig	2026-03-30 16:53:20 UTC
+++ node_modules/@nanocollective/nanocoder/node_modules/node-llama-cpp/dist/bindings/utils/cloneLlamaCppRepo.js
@@ -123,11 +123,11 @@ export async function isLlamaCppRepoCloned(waitForLock
         await waitForLockfileRelease({ resourcePath: llamaCppDirectory });
     else if (await isLockfileActive({ resourcePath: llamaCppDirectory }))
         return false;
-    const [repoGitExists, releaseInfoFileExists] = await Promise.all([
-        fs.pathExists(path.join(llamaCppDirectory, ".git")),
+    const [repoDirExists, releaseInfoFileExists] = await Promise.all([
+        fs.pathExists(llamaCppDirectory),
         fs.pathExists(llamaCppDirectoryInfoFilePath)
     ]);
-    return repoGitExists && releaseInfoFileExists;
+    return repoDirExists && releaseInfoFileExists;
 }
 export async function ensureLlamaCppRepoIsCloned({ progressLogs = true } = {}) {
     if (await isLlamaCppRepoCloned(true))
