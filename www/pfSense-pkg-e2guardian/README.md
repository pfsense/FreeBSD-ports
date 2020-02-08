# E2guardian Unofficial packages for pfSense software

As many people knows, Netgate has removed a lot of packages from official repo since pfSense® 2.3. 

This repo updates some packages for newer pfSense software versions with manual procedure installs.

This is not supported by Netgate or pfSense team. Use it at your own risk.

Feedbacks and contributions are always welcome.

# Install instructions

Enable unofficial repo under pfSense 2.4 by running the command bellow from either SSH or via Diagnostics > Command Prompt > Execute Shell Command.

fetch -q -o /usr/local/etc/pkg/repos/Unofficial.conf https://raw.githubusercontent.com/marcelloc/Unofficial-pfSense-packages/master/Unofficial.24.conf

E2guardian can be used to directly connect to the internet, or it can be used with another upstream proxy such as Squid for local file caching.

http://www.shallalist.de/Downloads/shallalist.tar.gz is one of compatible blacklists for e2guardian. Configure it under blacklist tab if you have any problems. The install script should already download the black list and get it setup for you.

Once it finishes, you can locate the E2Guardian package under Services > E2Guardian Proxy. If you do not see the menu after it finishes, try to install any pfSense package from GUI, like cron for example and it should appear.
