--- yc.el.orig	2020-09-25 04:51:12 UTC
+++ yc.el
@@ -393,7 +393,9 @@ OBJ $B$rJV5Q$9$k!#(B"
 		       (error nil)))))))
   (when (processp yc-server)
     (put 'yc-server 'init nil)
-    (process-kill-without-query yc-server)
+    (if (boundp 'process-kill-without-query)
+	(process-kill-without-query yc-server)
+      (set-process-query-on-exit-flag yc-server nil))
     (when yc-debug
       (unwind-protect
 	  (progn
@@ -1736,6 +1738,7 @@ OBJ $B$rJV5Q$9$k!#(B"
 				   (error nil))))
 	    (yc-eval-sexp (car expr)))))
       (setq files (cdr files)))
+    (message "")
     (if romkana-table
 	(setq yc-rH-conv-dic (yc-search-file-first-in-path
 			      romkana-table (list "." (getenv "HOME")
@@ -2071,7 +2074,7 @@ OBJ $B$rJV5Q$9$k!#(B"
 ;; $BJ8@a$r;XDj$7$J$$>l9g!"8=:_$NJ8@a$,BP>]$H$J$k(B
 ;; $BFI$_$r<hF@$7$?J8@a$O$=$NFI$_$r%-%c%C%7%e$9$k(B
 ;; cut $B$,(B $BHs(Bnil $B$N>l9g!";XDjJ8@a0J9_$NFI$_$r:o=|$9$k(B
-(defun yc-yomi (&optional idx &optional cut)
+(defun yc-yomi (&optional idx cut)
   (if (integerp idx)
       (yc-put-bunsetsu-yomi idx (yc-get-bunsetsu-yomi idx cut) cut)
     (yc-put-bunsetsu-yomi yc-mark (yc-get-bunsetsu-yomi yc-mark cut) cut)))
