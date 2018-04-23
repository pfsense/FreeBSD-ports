This custom telegraf-1.6.1 port is to address a buffer/memory issue in Telgraf <= 1.5.3 on amd64 FreeBSD 
and pfSense 11.1-RELEASE instances:

<https://redmine.pfsense.org/issues/8425>

<https://github.com/influxdata/telegraf/issues/3750>

I worked with an InfluxDB dev., and we found the issue. This modified port, fixes the
`[inputs.mem]` issue where memory measurement are not being reported, and throws 
` Error in plugin [inputs.mem]: error getting virtual memory info: cannot allocate memory` errors.

This port also installs the latest Telegraf version (1.6.1), which has a host of new and add'l external
golang deps not defined in the current Q2018 FreeBSD ports upstream version (telegraf v1.5.3).

Build and use at your own risk.
