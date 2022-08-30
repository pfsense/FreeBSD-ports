--- library/SimplePie/Parse/Date.php.orig	2022-08-30 13:53:51 UTC
+++ library/SimplePie/Parse/Date.php
@@ -541,8 +541,8 @@ class SimplePie_Parse_Date
 	 */
 	public function __construct()
 	{
-		$this->day_pcre = '(' . implode(array_keys($this->day), '|') . ')';
-		$this->month_pcre = '(' . implode(array_keys($this->month), '|') . ')';
+		$this->day_pcre = '(' . implode('|', array_keys($this->day)) . ')';
+		$this->month_pcre = '(' . implode('|', array_keys($this->month)) . ')';
 
 		static $cache;
 		if (!isset($cache[get_class($this)]))
