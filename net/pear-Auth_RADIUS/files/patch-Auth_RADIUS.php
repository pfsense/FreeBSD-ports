--- Auth/RADIUS.php.orig	2015-02-09 23:26:02 UTC
+++ Auth/RADIUS.php
@@ -280,6 +280,11 @@ class Auth_RADIUS {
     {
     }
     
+	/* Return our know attributes array received from the server. */
+	public function listAttributes() {
+		return $this->attributes;
+	}
+
     /**
      * Puts standard attributes.
      */ 
@@ -295,11 +300,7 @@ class Auth_RADIUS {
             $var = $GLOBALS['HTTP_SERVER_VARS'];
         }
                 
-        $this->putAttribute(RADIUS_NAS_IDENTIFIER, isset($var['HTTP_HOST']) ? $var['HTTP_HOST'] : 'localhost');
-        $this->putAttribute(RADIUS_NAS_PORT_TYPE, RADIUS_VIRTUAL);
-        $this->putAttribute(RADIUS_SERVICE_TYPE, RADIUS_FRAMED);
-        $this->putAttribute(RADIUS_FRAMED_PROTOCOL, RADIUS_PPP);
-        $this->putAttribute(RADIUS_CALLING_STATION_ID, isset($var['REMOTE_HOST']) ? $var['REMOTE_HOST'] : '127.0.0.1');
+        $this->putAttribute(RADIUS_SERVICE_TYPE, RADIUS_LOGIN);
     }
     
     /**
@@ -384,13 +385,13 @@ class Auth_RADIUS {
     {
         $req = radius_send_request($this->res);
         if (!$req) {
-            throw new Auth_RADIUS_Exception('Error sending request: ' . $this->getError());
+            return PEAR::raiseError(gettext('Error sending request:') . ' ' . $this->getError());
         }
 
         switch($req) {
         case RADIUS_ACCESS_ACCEPT:
             if (is_subclass_of($this, 'auth_radius_acct')) {
-                throw new Auth_RADIUS_Exception('RADIUS_ACCESS_ACCEPT is unexpected for accounting');
+                return PEAR::raiseError(gettext('RADIUS_ACCESS_ACCEPT is unexpected for accounting'));
             }
             return true;
 
@@ -399,12 +400,12 @@ class Auth_RADIUS {
             
         case RADIUS_ACCOUNTING_RESPONSE:
             if (is_subclass_of($this, 'auth_radius_pap')) {
-                throw new Auth_RADIUS_Exception('RADIUS_ACCOUNTING_RESPONSE is unexpected for authentication');
+                return PEAR::raiseError(gettext('RADIUS_ACCOUNTING_RESPONSE is unexpected for authentication'));
             }
             return true;
 
         default:
-            throw new Auth_RADIUS_Exception("Unexpected return value: $req");
+            return PEAR::raiseError(sprintf(gettext("Unexpected return value: %s"),$req));
         }    
         
     }
@@ -464,7 +465,10 @@ class Auth_RADIUS {
                 break;
 
             case RADIUS_CLASS:
-                $this->attributes['class'] = radius_cvt_string($data);
+                if (!is_array($this->attributes['class'])) {
+                    $this->attributes['class'] = array();
+                }
+                $this->attributes['class'][] = radius_cvt_string($data);
                 break;
 
             case RADIUS_FRAMED_PROTOCOL:
@@ -536,9 +540,179 @@ class Auth_RADIUS {
                         $this->attributes['ms_primary_dns_server'] = radius_cvt_string($datav);
                         break;
                     }
+                } elseif ($vendor == 1584) {
+                    switch ($attrv) {
+                    case 102:
+                        $this->attributes['ces_group'] =
+                          radius_cvt_string($datav);
+                        break;
+                    }
+                } elseif ($vendor == 3309) { /* RADIUS_VENDOR_NOMADIX */
+                    switch ($attrv) {
+                    case 1: /* RADIUS_NOMADIX_BW_UP */
+                        $this->attributes['bw_up'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 2: /* RADIUS_NOMADIX_BW_DOWN */
+                        $this->attributes['bw_down'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 3: /* RADIUS_NOMADIX_URL_REDIRECTION */
+                        $this->attributes['url_redirection'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 5: /* RADIUS_NOMADIX_EXPIRATION */
+                        $this->attributes['expiration'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 7: /* RADIUS_NOMADIX_MAXBYTESUP */
+                        $this->attributes['maxbytesup'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 8: /* RADIUS_NOMADIX_MAXBYTESDOWN */
+                        $this->attributes['maxbytesdown'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 10: /* RADIUS_NOMADIX_LOGOFF_URL */
+                        $this->attributes['url_logoff'] =
+                          radius_cvt_string($datav);
+                        break;
+                    }
+                } elseif ($vendor == 14122) { /* RADIUS_VENDOR_WISPr */
+                    switch ($attrv) {
+                    case 1: /* WISPr-Location-ID */
+                        $this->attributes['location_id'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 2: /* WISPr-Location-Name */
+                        $this->attributes['location_name'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 3: /* WISPr-Logoff-URL */
+                        $this->attributes['url_logoff'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 4: /* WISPr-Redirection-URL */
+                        $this->attributes['url_redirection'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 5: /* WISPr-Bandwidth-Min-Up */
+                        $this->attributes['bw_up_min'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 6: /* WISPr-Bandwidth-Min-Down */
+                        $this->attributes['bw_down_min'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 7: /* WISPr-Bandwidth-Max-Up */
+                        $this->attributes['bw_up'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 8: /* WISPr-Bandwidth-Max-Down */
+                        $this->attributes['bw_down'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 9: /* WISPr-Session-Terminate-Time */
+                        $this->attributes['session_terminate_time'] =
+                          radius_cvt_string($datav);
+                        break;
+                    case 10: /* WISPr-Session-Terminate-End-Of-Day */
+                        $this->attributes['session_terminate_endofday'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 11: /* WISPr-Billing-Class-Of-Service */
+                        $this->attributes['billing_class_of_service'] =
+                          radius_cvt_string($datav);
+                        break;
+                    }
+                } elseif ($vendor == 14559) { /* RADIUS_VENDOR_ChilliSpot */
+                    switch ($attrv) {
+                    case 4: /* ChilliSpot-Bandwidth-Max-Up */
+                        $this->attributes['bw_up'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 5: /* ChilliSpot-Bandwidth-Max-Down */
+                        $this->attributes['bw_down'] =
+                          radius_cvt_int($datav);
+                        break;
+                    }
+                } elseif ($vendor == 9) { /* RADIUS_VENDOR_CISCO */
+                    switch ($attrv) {
+                    case 1: /* Cisco-AVPair */
+                        if (!is_array(
+                            $this->attributes['ciscoavpair'])) {
+                            $this->attributes['ciscoavpair'] =
+                              array();
+                        }
+                        $this->attributes['ciscoavpair'][] =
+                          radius_cvt_string($datav);
+                        break;
+                    }
+                } elseif ($vendor == 8744) { /* Colubris / HP MSM wireless */
+                    /*
+                     * documented at
+                     * http://bizsupport1.austin.hp.com/bc/docs/support/SupportManual/c02704528/c02704528.pdf pg 15-67
+                     */
+                    if ($attrv == 0) { /* Colubris AV-Pair */
+                        $datav = explode('=', $datav);
+                        switch ($datav[0]) {
+                        case 'max-input-rate':
+                            /*
+                             * Controls the data rate [kbps] at
+                             * which traffic can be transferred
+                             * from the user to the [router]
+                             */
+                            $this->attributes['bw_up'] =
+                              radius_cvt_int($datav[1]);
+                            break;
+                        case 'max-output-rate':
+                            /*
+                             * Controls the data rate [kbps] at
+                             * which traffic can be transferred
+                             * from the [router] to the user
+                             */
+                            $this->attributes['bw_down'] =
+                              radius_cvt_int($datav[1]);
+                            break;
+                        case 'max-input-octets':
+                            $this->attributes['maxbytesup'] =
+                              radius_cvt_int($datav[1]);
+                            break;
+                        case 'max-output-octets':
+                            $this->attributes['maxbytesdown'] =
+                              radius_cvt_int($datav[1]);
+                            break;
+                        case 'welcome-url':
+                            $this->attributes['url_redirection'] =
+                              radius_cvt_string($datav[1]);
+                            break;
+                        case 'goodbye-url':
+                            $this->attributes['url_logoff'] =
+                              radius_cvt_string($datav[1]);
+                            break;
+                        }
+                    }
+                } elseif ($vendor == 13644) { /* Netgate */
+                    switch ($attrv) {
+                    case 1: /* pfSense-Bandwidth-Max-Up */
+                        $this->attributes['bw_up'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 2: /* pfSense-Bandwidth-Max-Down */
+                        $this->attributes['bw_down'] =
+                          radius_cvt_int($datav);
+                        break;
+                    case 3: /* pfSense-Max-Total-Octets */
+                        $this->attributes['maxbytes'] =
+                          radius_cvt_int($datav);
+                        break;
+                    }
                 }
                 break;
-                
+            case 85: /* Acct-Interim-Interval: RFC 2869 */
+                $this->attributes['interim_interval'] = radius_cvt_int($data);
+                break;
+
             }
         }    
 
@@ -935,11 +1109,16 @@ class Auth_RADIUS_Acct extends Auth_RADIUS
      */ 
     function putAuthAttributes()
     {
-        $this->putAttribute(RADIUS_ACCT_SESSION_ID, $this->session_id);
+        if (isset($this->username)) {
+            $this->putAttribute(RADIUS_USER_NAME, $this->username);
+        }
+
         $this->putAttribute(RADIUS_ACCT_STATUS_TYPE, $this->status_type);
-        if (isset($this->session_time) && $this->status_type == RADIUS_STOP) {
+
+        if (isset($this->session_time)) {
             $this->putAttribute(RADIUS_ACCT_SESSION_TIME, $this->session_time);
         }
+
         if (isset($this->authentic)) {
             $this->putAttribute(RADIUS_ACCT_AUTHENTIC, $this->authentic);
         }
@@ -1003,4 +1182,22 @@ class Auth_RADIUS_Acct_Update extends Auth_RADIUS_Acct
     var $status_type = RADIUS_UPDATE;
 }
 
-class Auth_RADIUS_Exception extends Exception {}
\ No newline at end of file
+class Auth_RADIUS_Exception extends Exception {}
+
+class Auth_RADIUS_Acct_On extends Auth_RADIUS_Acct
+{
+    /*
+     * Defines the type of the accounting request.
+     * It is set to RADIUS_ACCOUNTING_ON by default in this class.
+     */
+    var $status_type = RADIUS_ACCOUNTING_ON;
+}
+
+class Auth_RADIUS_Acct_Off extends Auth_RADIUS_Acct
+{
+    /*
+     * Defines the type of the accounting request.
+     * It is set to RADIUS_ACCOUNTING_OFF by default in this class.
+     */
+    var $status_type = RADIUS_ACCOUNTING_OFF;
+}
