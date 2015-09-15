--- sapi/fpm/fpm/fpm_main.c.orig	2014-03-05 04:18:00.000000000 -0600
+++ sapi/fpm/fpm/fpm_main.c	2014-04-29 06:11:11.000000000 -0500
@@ -1875,6 +1875,9 @@
 				return FPM_EXIT_SOFTWARE;
 			}
 
+			if (sapi_cgibin_getenv("NO_HEADERS", sizeof("NO_HEADERS") - 1 TSRMLS_CC))
+				SG(request_info).no_headers = 1;
+
 			/* check if request_method has been sent.
 			 * if not, it's certainly not an HTTP over fcgi request */
 			if (!SG(request_info).request_method) {
@@ -1930,6 +1933,76 @@
 
 			fpm_request_executing();
 
+			/* #!php support */
+			switch (file_handle.type) {
+				case ZEND_HANDLE_FD:
+					if (file_handle.handle.fd < 0) {
+						break;
+					}
+					file_handle.type = ZEND_HANDLE_FP;
+					file_handle.handle.fp = fdopen(file_handle.handle.fd, "rb");
+					/* break missing intentionally */
+				case ZEND_HANDLE_FP:
+					if (!file_handle.handle.fp ||
+					    (file_handle.handle.fp == stdin)) {
+						break;
+					}
+					c = fgetc(file_handle.handle.fp);
+					if (c == '#') {
+						while (c != '\n' && c != '\r' && c != EOF) {
+							c = fgetc(file_handle.handle.fp);	/* skip to end of line */
+						}
+						/* handle situations where line is terminated by \r\n */
+						if (c == '\r') {
+							if (fgetc(file_handle.handle.fp) != '\n') {
+								long pos = ftell(file_handle.handle.fp);
+								fseek(file_handle.handle.fp, pos - 1, SEEK_SET);
+							}
+						}
+						CG(start_lineno) = 2;
+					} else {
+						rewind(file_handle.handle.fp);
+					}
+					break;
+				case ZEND_HANDLE_STREAM:
+					c = php_stream_getc((php_stream*)file_handle.handle.stream.handle);
+					if (c == '#') {
+						while (c != '\n' && c != '\r' && c != EOF) {
+							c = php_stream_getc((php_stream*)file_handle.handle.stream.handle);	/* skip to end of line */
+						}
+						/* handle situations where line is terminated by \r\n */
+						if (c == '\r') {
+							if (php_stream_getc((php_stream*)file_handle.handle.stream.handle) != '\n') {
+								long pos = php_stream_tell((php_stream*)file_handle.handle.stream.handle);
+								php_stream_seek((php_stream*)file_handle.handle.stream.handle, pos - 1, SEEK_SET);
+							}
+						}
+						CG(start_lineno) = 2;
+					} else {
+						php_stream_rewind((php_stream*)file_handle.handle.stream.handle);
+					}
+					break;
+				case ZEND_HANDLE_MAPPED:
+					if (file_handle.handle.stream.mmap.buf[0] == '#') {
+					    int i = 1;
+
+					    c = file_handle.handle.stream.mmap.buf[i++];
+						while (c != '\n' && c != '\r' && c != EOF) {
+							c = file_handle.handle.stream.mmap.buf[i++];
+						}
+						if (c == '\r') {
+							if (file_handle.handle.stream.mmap.buf[i] == '\n') {
+								i++;
+							}
+						}
+						file_handle.handle.stream.mmap.buf += i;
+						file_handle.handle.stream.mmap.len -= i;
+					}
+					break;
+				default:
+					break;
+			}
+
 			php_execute_script(&file_handle TSRMLS_CC);
 
 fastcgi_request_done:
