#!/usr/bin/env python
# To use:
#       python setup.py install
#

import os, string

try:
    import distutils
    from distutils import sysconfig
    from distutils.command.install import install
    from distutils.core import setup, Extension
except:
    raise SystemExit("Distutils problem")

install.sub_commands = [x for x in install.sub_commands if 'egg' not in x[0]]

tkversion = "%%TK_VER%%"
prefix = sysconfig.PREFIX
# Python 1.5 doesn't have os.getenv()?
x11base = os.environ['LOCALBASE'] or '/usr/X11R6'
inc_dirs = [prefix + "/include",
            prefix + "/include/tcl" + tkversion,
            prefix + "/include/tk" + tkversion,
            x11base + "/include"]
lib_dirs = [prefix + "/lib", x11base + "/lib"]
# use string.replace() for the benefit of Python 1.5 users
libs = ["tcl" + tkversion.replace(".", ""),
        "tk" + tkversion.replace(".", ""),
        "X11"]

setup(name = "Tkinter",
      description = "Tk Extension to Python",

      ext_modules = [Extension('_tkinter', ['_tkinter.c', 'tkappinit.c'],
                               define_macros=[('WITH_APPINIT', 1)],
                               include_dirs = inc_dirs,
                               libraries = libs,
                               library_dirs = lib_dirs)]
      )
