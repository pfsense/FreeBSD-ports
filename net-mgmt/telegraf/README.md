This custom telegraf-1.6.1 port is to address a buffer/memory issue in Telegraf <= 1.5.3 on amd64 FreeBSD 
and pfSense 11.1-RELEASE instances:

<https://redmine.pfsense.org/issues/8425>

<https://github.com/influxdata/telegraf/issues/3750>

I worked with an InfluxDB dev., and we found the issue. This modified port, fixes the
`[inputs.mem]` issue where memory measurement are not being reported, and throws 
` Error in plug-in [inputs.mem]: error getting virtual memory info: cannot allocate memory` errors.

This port also installs the latest Telegraf version (1.6.1), which has a host of new and add'l external
golang deps not defined in the current Q2018 FreeBSD ports upstream version (telegraf v1.5.3).

ALSO: This newer port adds PF metrics (`pfctl/pfstat`) to Telegraf/InfluxDB. See screenshot below. Not
sure why the pfSense package maintainer never enabled this native plug-in/input in the .inc config file. *shrug*

New PF state measurements supported:

- pf
    - entries (integer, count)
    - searches (integer, count)
    - inserts (integer, count)
    - removals (integer, count)

### Example Output:

```
> pfctl -s info
Status: Enabled for 0 days 00:26:05           Debug: Urgent

State Table                          Total             Rate
  current entries                        2               
  searches                           11325            7.2/s
  inserts                                5            0.0/s
  removals                               3            0.0/s
Counters
  match                              11226            7.2/s
  bad-offset                             0            0.0/s
  fragment                               0            0.0/s
  short                                  0            0.0/s
  normalize                              0            0.0/s
  memory                                 0            0.0/s
  bad-timestamp                          0            0.0/s
  congestion                             0            0.0/s
  ip-option                              0            0.0/s
  proto-cksum                            0            0.0/s
  state-mismatch                         0            0.0/s
  state-insert                           0            0.0/s
  state-limit                            0            0.0/s
  src-limit                              0            0.0/s
  synproxy                               0            0.0/s
```


Build and use at your own risk.

## Screenshot:
![alt text](http://techdocs.cuccio.us/telegraf-pf.png "Screenshot: Graph of PF State Tables")
