# pfSense-pkg-DNSleaktest
A DNS Leaktest package I made for pfSense Project.

## Installation
Currently I'm working on the processes of getting this package prepped to be submitted to and reviewed by the maintainers of the pfSense Project, so that it will be included in future releases. If it does get approved, you will be able to install this package through the pfSense Package Manager.

## Interface and Usage

### GUI (Initial):
![image](https://user-images.githubusercontent.com/73666574/209186555-3fcbfb3a-f0d8-4e64-8ace-dd8716ce9b15.png)
 - GUI can be opened through the "Diagnostics" dropdown in the pfSense Menubar

### GUI (Selected Network Interface and API):
![image](https://user-images.githubusercontent.com/73666574/209186971-adc8d089-f7e8-49bd-929f-c96fd01ed766.png)
 - **Source Interface**: Select an egress network interface (such as WAN or VPN Tunnel) to perform the test on
 - **API Domain**: Select the dns leak test API of your choice (currently only bash.ws is supported)

### GUI (Output after clicking "Scan" button):
![image](https://user-images.githubusercontent.com/73666574/209187303-ea3d4585-f8a5-4ba2-829f-640305c6d6fe.png)
- The results will be displayed to you. If more than one DNS server is detected, it will tell you that DNS may be leaking, so it will be up to you to determine if the DNS servers shown are the ones you intended on using, and if they are trustworthy.
- Based on the results and your assesment of them, take the appropriate steps to remediate if necessary.


## Action Items:
- [x] Interface and support for bash.ws dns leak testing
- [ ] Add support for other DNS Leak Testing APIs (dnsleaktest.com, etc)

## Contributions:
Contributions are welcome. Fork the repo, make your changes, create a diff file, and email the diff file and your GitHub username to luis@moraguez.com. If the changes are approved, you will be added as a contributor to the repo.

## Donations:
If this utility helped you with a project you're working on and you wish to make a donation, you can do so by clicking the donate button that follows. Thank you for your generosity and support!

<noscript><a href="https://liberapay.com/z3d6380/donate"><img alt="Donate using Liberapay" src="https://liberapay.com/assets/widgets/donate.svg"></a></noscript>
