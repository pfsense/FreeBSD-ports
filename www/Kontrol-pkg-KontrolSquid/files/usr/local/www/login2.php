<?php
// DO NOT CHANGE THIS FILE MANUALLY AS IT IS MANAGED BY SCRIPTS !!!
// KONTROL-UTM - 2016-2020 - KONNTROL TECNOLOGIA EPP, All rights reserved.
// LOGIN/AUTHENTICATION WEB PAGE used to capture credentials for Transparent Authentication.
// DO NOT CHANGE THIS FILE MANUALLY AS IT IS MANAGED BY SCRIPTS
error_reporting(E_ALL);
ini_set('display_errors', 'On');
// DOMAIN FQDN - DO NOT CHANGE THIS MANUALLY!!!!
define('DOMAIN_FQDN', 'kontrol.corp');
define('LDAP_SERVER', '192.168.0.30');

// INIT of Capturing Client's IP address
function get_client_ip_env() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';

    return $ipaddress;
}
$srcip =  get_client_ip_env();
			// echo $srcip ;
// END of Capturing Client's IP address

//Basic Login verification
if (isset($_POST['submit']))
{
    $user = strip_tags($_POST['username']) .'@'. DOMAIN_FQDN;
    $pass = stripslashes($_POST['password']);
    $conn = ldap_connect("ldaps://". LDAP_SERVER ."/");
    if (!$conn)
        $err = 'Could not connect to LDAP server';
    else
    {
//        define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);  //Already defined in PHP 5.x  versions
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        $bind = @ldap_bind($conn, $user, $pass);
        ldap_get_option($conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
        if (!empty($extended_error))
        {
            $errno = explode(',', $extended_error);
            $errno = $errno[2];
            $errno = explode(' ', $errno);
            $errno = $errno[2];
            $errno = intval($errno);
            if ($errno == 532)
                $err = 'Unable to login: Password expired';
        }
        elseif ($bind)
        {
      //determine the LDAP Path from Active Directory details
            $base_dn = array("CN=Users,DC=". join(',DC=', explode('.', DOMAIN_FQDN)),
                "OU=Users,OU=People,DC=". join(',DC=', explode('.', DOMAIN_FQDN)));
            $result = ldap_search(array($conn,$conn), $base_dn, "(cn=*)");
            if (!count($result))
                $err = 'Result: '. ldap_error($conn);
            else
            {
				 /* ADD post login code here */
				$user_final = substr($user, 0, strrpos($user, '@'));
				//$user_final = base64_encode($user);

//INIT of TIMESTAMP calculation + N hours Expiration Time - In seconds.  2 Hours e.g.   $timestamp = time() + (2 * 60 * 60);
$timestamp = time() + (1 * 60 * 60);
//END of TIMESTAMP calculation


// INIT OF SQL CODE
class kontroldb extends SQLite3 {
      function __construct() {
         $this->open('/root/kontrolid.db');
      }
   }

   $db = new kontroldb();
   if(!$db){
      echo $db->lastErrorMsg();
   } else {
      echo "";
   }

   $sql =<<<EOF

      REPLACE INTO kontrolid VALUES ('$srcip', '$user_final', '$timestamp');
EOF;

   $ret = $db->exec($sql);
   if(!$ret) {
      echo $db->lastErrorMsg();
   } else {
# CHANGE THE AMOUNT OF TIME ACCORDING TO YOUR SETTINGS ON TIMESTAMP CALC.
      echo "Success - Authentication Valid for 1 Hour\n";
   }
   $db->close();

// END OF SQL CODE

            }
        }
    }
    // session OK, redirect to home page
    if (isset($_SESSION['redir']))
    {
        header('Location: /');
        exit();
    }
    elseif (!isset($err)) $err = 'Result: '. ldap_error($conn);
    ldap_close($conn);
}
?>
<!DOCTYPE html><head><title>KONTROL-UTM</title></head>
<style>
* { font-family: Calibri, Tahoma, Arial, sans-serif; }
.errmsg { color: red; }
#loginbox { font-size: 12px; }
</style>

<body>
<hr/>
<div align="center">
<div align="center" style="width:500px;height:145px;border:3px solid #000;">
<br/>
<img id="imghdr" src="/new-konntrol-logo1.png"/>
<div align="center"><h1>Authentication Portal</h1>
</div>

<hr/>
<h2>Identify Yourself to Continue Browsing</h2>

<hr/>

<div style="margin:10px 0;"></div>
<div title="KONTROL-UTM  Enter your credentials" style="width:400px" id="loginbox">
    <div style="padding:10px 0 10px 60px">

    <form action="/login2.php" id="login" method="post">

	   <table>
	   <?php if (isset($err)) echo '<tr><td colspan="2" class="errmsg">'. $err .'</td></tr>'; ?>
	            <tr>
                <td><div align="center"/><b>Login:<b/></td>
                <td><input type="text" name="username" style="border: 1px solid #ccc;" autocomplete="off"/></td>
            </tr>
            <tr>
                <td><b>Password:<b/></td>
                <td><input type="password" name="password" style="border: 1px solid #ccc;" autocomplete="off"/></td>
            </tr>
        </table>
        <input class="button" type="submit" name="submit" value="Login" />
    </form>
    </div>
</div>
</div>
</body></html>
