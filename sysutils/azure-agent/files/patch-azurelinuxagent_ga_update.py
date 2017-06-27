--- azurelinuxagent/ga/update.py.orig	2017-06-27 16:48:42 UTC
+++ azurelinuxagent/ga/update.py
@@ -119,7 +119,7 @@ class UpdateHandler(object):
         latest_agent = self.get_latest_agent()
         if latest_agent is None:
             logger.info(u"Installed Agent {0} is the most current agent", CURRENT_AGENT)
-            agent_cmd = "python -u {0} -run-exthandlers".format(sys.argv[0])
+            agent_cmd = "%%PYTHON_CMD%% -u {0} -run-exthandlers".format(sys.argv[0])
             agent_dir = os.getcwd()
             agent_name = CURRENT_AGENT
             agent_version = CURRENT_VERSION
