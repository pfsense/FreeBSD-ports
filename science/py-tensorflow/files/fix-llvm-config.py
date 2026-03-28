#!/usr/bin/env python3
"""Patch LLVM config.bzl to use @platforms//os:freebsd condition."""
import os
import sys

f = sys.argv[1]
r = os.path.realpath(f)
c = open(r).read()
c = c.replace(
    '"@bazel_tools//src/conditions:freebsd": posix_defines,',
    '"@platforms//os:freebsd": posix_defines,')
c = c.replace(
    '"//conditions:default": native_arch_defines("X86", "x86_64-unknown-linux-gnu"),',
    '"@platforms//os:freebsd": native_arch_defines("X86", "x86_64-unknown-freebsd14-elf"),\n'
    '    "//conditions:default": native_arch_defines("X86", "x86_64-unknown-linux-gnu"),')
os.chmod(r, 0o644)
open(r, 'w').write(c)
