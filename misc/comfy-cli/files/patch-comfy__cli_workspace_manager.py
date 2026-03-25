--- comfy_cli/workspace_manager.py.orig	2026-03-22 01:45:24 UTC
+++ comfy_cli/workspace_manager.py
@@ -260,9 +260,9 @@ class WorkspaceManager:
         if self.use_recent is None:
             recent_workspace = self.config_manager.get(constants.CONFIG_KEY_RECENT_WORKSPACE)
             if recent_workspace and check_comfy_repo(recent_workspace)[0]:
                 return recent_workspace, WorkspaceType.RECENT
-            else:
+            elif recent_workspace:
                 print(
                     f"[bold red]warn: The recent workspace {recent_workspace} is not a valid ComfyUI path.[/bold red]"
                 )
 
