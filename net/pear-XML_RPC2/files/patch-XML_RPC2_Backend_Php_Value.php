--- XML/RPC2/Backend/Php/Value.php.orig	2018-06-20 09:12:12 UTC
+++ XML/RPC2/Backend/Php/Value.php
@@ -147,7 +147,7 @@ abstract class XML_RPC2_Backend_Php_Valu
                         do {
                             $previous = $keys[$i];
                             $i++;
-                            if (array_key_exists($i, $keys) && ($keys[$i] !== $keys[$i - 1] + 1)) $explicitType = 'struct';
+                            if (array_key_exists($i, $keys) && ((int) $keys[$i] !== (int) $keys[$i - 1] + 1)) $explicitType = 'struct';
                         } while (array_key_exists($i, $keys) && $explicitType == 'array');
                     }
                     break;
