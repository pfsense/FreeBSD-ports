--- tests/frontend/create.sh.orig	2025-12-23 10:23:55 UTC
+++ tests/frontend/create.sh
@@ -543,6 +543,7 @@ EOF
 		-e empty \
 		-s exit:0 \
 		pkg create -M ./+MANIFEST -r ${TMPDIR}
+	cp test-1.pkg /tmp
 
 	cat << EOF > output.ucl
 name = "test";
@@ -867,12 +868,12 @@ create_from_plist_with_variables_body() {
 	genmanifest
 	genplist "
 @var key1
-@var key2 
+@var key2
 @var key3 plop
 %%key1%%file1
 %%key2%%file2
 %%key3%%
-@var key3 @comment 
+@var key3 @comment
 %%key3%% file3"
 
 	atf_check \
