#!/usr/bin/env python
import xml.etree.ElementTree as ET

class simplemetric:
	template = """# HELP {name} {help}
# TYPE {name} gauge
{series}"""
	def __init__(self, name, help):
		self.name = name
		self.help = help
		self.series = []
	def __str__(self):
		return simplemetric.template.format(name=self.name, help=self.help, series='\n'.join(self.series))
	def __repr__(self):
		return self.__str__()
	def add(self, val, **labelpairs):
		lvs = ['{key}="{value}"'.format(key=key, value=value) for key,value in list(labelpairs.items())]
		self.series.append('{name}{{{labels}}} {val}'.format(name=self.name, labels=','.join(lvs), val=val))

metrics = {
	'up': simplemetric('node_pfsense_interface_up','1 if interface is enabled, else 0.'),
	'info': simplemetric('node_pfsense_interface_info', 'Information about the interface. Always 1.')
}

root = ET.parse('/conf/config.xml')
for elem in root.find("interfaces"):
	pf_name = elem.tag
	if_name = elem.find('if').text
	descr = elem.find('descr').text
	enabled = 1 if elem.find('enable') is not None else 0
	metrics['up'].add(enabled, name=pf_name)
	metrics['info'].add(enabled, description=descr, interface=if_name, name=pf_name)

textfile = open('/var/tmp/node_exporter/pfsense.prom','w')
textfile.write('\n'.join(str(m) for m in list(metrics.values())))
textfile.write('\n') # Ensure trailing newline
