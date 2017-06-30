# vHosts

## Name/IP Based Virtual Host Web Server
---
A web server package to host HTML, Javascript, CSS, and PHP as Name or IP Based virtual hosts. 
This is a full rewrite/port of the pre-2.3.0 vhosts package using the [nginx](https://wiki.nginx.org/)
web server installed with pfSense 2.3.0 and later versions.

This tool was ported to provide a simple way of returning a single page, for all requests, to a web server
that sits behind the router and requires maintenance. This is a very limited requirement and using 
this package on the router for anything more robust than simple serving of pages is not recommended.

## Configuration

The vHosts server creates an instance of the nginx web server using a configuration file built from the
list of vHosts definitions. The Certificates list contains SSL/TLS certificates that may be bound to hosts
requiring secure connections.

### Hosts

* **Directory Name** 
<br>Document root directory name in `/usr/local/vhosts`. The default documents are `index.html`,
`index.htm`, and `index.php`. If none exists, `index.php` is created to display the current PHP status.
<br><br>**Note:** Other than creation of the default `index.php`, the vHosts package does not manage any
of the files in a host root directory. Pages must be added and removed manually.

* **IP Address**
<br>Host IP address. Must be one of the IP addresses bound to the router.

* **Port**
<br>Port number for binding to the IP address.

* **Host Name(s)**
<br>Space separated list of Name-Based Host(s). Not required for an IP-Based host.

* **Secure Certificate**
<br>The certificate common name (CN) of the certificate selected to secure the host. Certificates must 
be loaded to the Certificates list before they can be assigned to a host.

* **Custom Configuration**
<br>Additional configuration parameters to be included in the nginx configuration. Simple parameters are
recommended to prevent creating errors in the configuration.
<br><br>**Note:** If the vHosts service fails to start, configuration errors will be found in `/var/log/nginx/error.log`.
<br><br>Examples:
  - `return 301 https://$host$request_uri;`
  <br>Redirect the request to "https:".
  
  - `rewrite ^.*$ /allpages.html last`
  <br>Rewrite the URI to return a single page for all requests.

### Certificates

For secure hosts, the SSL/TLS certificate must be loaded into the certificates list before it can be
assigned to a host. Multiple hosts may be bound to a single certificate.

Certificates may be added or updated by dropping/pasting the Certificate and Certificate Key in X.509
PEM format or by loading the PKCS#12 file (.p12/.pfx) containing the key-pair.

