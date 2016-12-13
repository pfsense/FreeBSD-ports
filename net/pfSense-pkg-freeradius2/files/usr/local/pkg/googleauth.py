#!/usr/local/bin/python2.7
# Copyright: www.brool.com (http://www.brool.com/post/using-google-authenticator-for-your-website/)
# License: CC0 1.0 Universal License

import sys
import time
import struct
import hmac
import hashlib
import base64
import syslog
 
def authenticate(username, secretkey, pin, code_attempt):

    if code_attempt.startswith(pin,0, len(pin)) == False:
         syslog.syslog(syslog.LOG_ERR, "freeRADIUS: Google Authenticator - Authentication failed. User: " + username + ", Reason: wrong PIN")
         return False

    code_attempt = code_attempt[len(pin):]
    tm = int(time.time() / 30)
 
    secretkey = base64.b32decode(secretkey)
 
    # try 30 seconds behind and ahead as well
    for ix in [-1, 0, 1]:
        # convert timestamp to raw bytes
        b = struct.pack(">q", tm + ix)
 
        # generate HMAC-SHA1 from timestamp based on secret key
        hm = hmac.HMAC(secretkey, b, hashlib.sha1).digest()
 
        # extract 4 bytes from digest based on LSB
        offset = ord(hm[-1]) & 0x0F
        truncatedHash = hm[offset:offset+4]
 
        # get the code from it
        code = struct.unpack(">L", truncatedHash)[0]
        code &= 0x7FFFFFFF;
        code %= 1000000;
 
        if ("%06d" % code) == str(code_attempt):
            syslog.syslog(syslog.LOG_NOTICE, "freeRADIUS: Google Authenticator - Authentication successful for user: " + username)
            return True

    syslog.syslog(syslog.LOG_ERR, "freeRADIUS: Google Authenticator - Authentication failed. User: " + username + ", Reason: wrong tokencode")
    return False


# Check the length of the parameters
if len(sys.argv) != 5:
        syslog.syslog(syslog.LOG_ERR, "freeRADIUS: Google Authenticator - wrong syntax - USAGE: googleauth.py Username, Secret-Key, PIN, Auth-Attempt")
        exit(1)


auth = authenticate(sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4])

if auth == True:
   exit(0)

exit(1)
