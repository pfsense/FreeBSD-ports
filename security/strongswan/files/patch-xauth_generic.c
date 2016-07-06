--- src/libcharon/plugins/xauth_generic/xauth_generic.c.orig	2016-04-22 20:01:35 UTC
+++ src/libcharon/plugins/xauth_generic/xauth_generic.c
@@ -13,10 +13,15 @@
  * for more details.
  */
 
+#include <sys/types.h>
+#include <sys/wait.h>
+
 #include "xauth_generic.h"
 
 #include <daemon.h>
 #include <library.h>
+#include <unistd.h>
+#include <errno.h>
 
 typedef struct private_xauth_generic_t private_xauth_generic_t;
 
@@ -41,6 +46,103 @@ struct private_xauth_generic_t {
 	identification_t *peer;
 };
 
+/**
+ * Add/Append to the environment pointer name=value pair
+ */
+static char *
+name_value_pair(const char *name, char *value)
+{
+	char *envitem;
+
+        envitem = malloc(strlen(name) + 1 + strlen(value) + 1);
+        if (envitem == NULL) {
+                DBG1(DBG_IKE,
+                    "Cannot allocate memory.");
+                return NULL;
+        }
+        sprintf(envitem, "%s=%s", name, value);
+
+        return envitem;
+}
+
+/**
+ * Convert configuration attribute content to a null-terminated string
+ */
+static void attr2string(char *buf, size_t len, chunk_t chunk)
+{
+	if (chunk.len && chunk.len < len)
+	{
+		snprintf(buf, len, "%.*s", (int)chunk.len, chunk.ptr);
+	}
+}
+
+/**
+ * Authenticate a username/password using script
+ */
+static bool
+authenticate(char *service, chunk_t username, chunk_t password, char *authcfg)
+{
+	pid_t pid, rpid;
+	char *envp[4] = { NULL, NULL, NULL, NULL };
+	char *argv[3] = { NULL, NULL, NULL };
+	char user[128] = "", pass[128] = "";
+	int envc = 4;
+	int ret;
+
+	attr2string(user, sizeof(user), username);
+	attr2string(pass, sizeof(pass), password);
+
+	envp[0] = name_value_pair("username", user);
+	envp[1] = name_value_pair("password", pass);
+	envp[2] = name_value_pair("authcfg", authcfg);
+	envp[3] = NULL;
+
+	argv[0] = service;
+	argv[1] = service;
+	argv[2] = NULL;
+
+	pid = fork();
+	switch (pid) {
+	case 0:
+		execve(argv[0], argv, envp);
+		DBG1(DBG_IKE, "XAUTH-SCRIPT failed to execute script '%s'.", service);
+		 _exit (127);
+		break;
+	case -1:
+		ret = -1;
+		break;
+	default:
+		do {
+                        rpid = waitpid (pid, &ret, 0);
+                } while (rpid == -1 && errno == EINTR);
+                if (rpid != pid)
+                        ret = -1;
+
+		break;
+	}
+
+	if (WIFEXITED(ret)) {
+                if (WEXITSTATUS(ret) != 0)
+                        ret = -1;
+                else
+                        ret = 0;
+        }
+	if (ret == 0)
+		DBG1(DBG_IKE, "XAuth-SCRIPT succeeded for user '%s'.", user);
+	else
+		DBG1(DBG_IKE, "XAuth-SCRIPT failed for user '%s' with return status: %d.",
+			 user, ret);
+
+	if (envp[0] != NULL)
+		free(envp[0]);
+	if (envp[1] != NULL)
+		free(envp[1]);
+	if (envp[2] != NULL)
+		free(envp[2]);
+
+	return ret;
+}
+
 METHOD(xauth_method_t, initiate_peer, status_t,
 	private_xauth_generic_t *this, cp_payload_t **out)
 {
@@ -137,6 +239,7 @@ METHOD(xauth_method_t, process_server, s
 	chunk_t user = chunk_empty, pass = chunk_empty;
 	status_t status = FAILED;
 	int tried = 0;
+	char *service, *authcfg;
 
 	enumerator = in->create_attribute_enumerator(in);
 	while (enumerator->enumerate(enumerator, &attr))
@@ -176,29 +279,45 @@ METHOD(xauth_method_t, process_server, s
 		pass.len -= 1;
 	}
 
-	enumerator = lib->credmgr->create_shared_enumerator(lib->credmgr,
-										SHARED_EAP, this->server, this->peer);
-	while (enumerator->enumerate(enumerator, &shared, NULL, NULL))
-	{
-		if (chunk_equals_const(shared->get_key(shared), pass))
-		{
+	/* XXX: Maybe support even FCGI calling here? */
+	service = lib->settings->get_str(lib->settings,
+		"%s.plugins.xauth-generic.script", NULL, lib->ns);
+	if (service) {
+		authcfg = lib->settings->get_str(lib->settings,
+			"%s.plugins.xauth-generic.authcfg",
+			NULL, lib->ns);
+		if (!authenticate(service, user, pass, authcfg))
 			status = SUCCESS;
-			break;
+		else {
+			DBG1(DBG_IKE, "Could not authenticate with XAuth secrets for '%Y' - '%Y' ",
+				 this->server, this->peer);
 		}
-		tried++;
-	}
-	enumerator->destroy(enumerator);
-	if (status != SUCCESS)
-	{
-		if (!tried)
+	} else {
+
+		enumerator = lib->credmgr->create_shared_enumerator(lib->credmgr,
+											SHARED_EAP, this->server, this->peer);
+		while (enumerator->enumerate(enumerator, &shared, NULL, NULL))
 		{
-			DBG1(DBG_IKE, "no XAuth secret found for '%Y' - '%Y'",
-				 this->server, this->peer);
+			if (chunk_equals_const(shared->get_key(shared), pass))
+			{
+				status = SUCCESS;
+				break;
+			}
+			tried++;
 		}
-		else
+		enumerator->destroy(enumerator);
+		if (status != SUCCESS)
 		{
-			DBG1(DBG_IKE, "none of %d found XAuth secrets for '%Y' - '%Y' "
-				 "matched", tried, this->server, this->peer);
+			if (!tried)
+			{
+				DBG1(DBG_IKE, "no XAuth secret found for '%Y' - '%Y'",
+					 this->server, this->peer);
+			}
+			else
+			{
+				DBG1(DBG_IKE, "none of %d found XAuth secrets for '%Y' - '%Y' "
+					 "matched", tried, this->server, this->peer);
+			}
 		}
 	}
 	return status;
