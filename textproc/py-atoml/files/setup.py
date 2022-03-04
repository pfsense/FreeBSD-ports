
# -*- coding: utf-8 -*-
from setuptools import setup

import codecs

with codecs.open('README.md', encoding="utf-8") as fp:
    long_description = fp.read()

setup_kwargs = {
    'name': 'atoml',
    'version': '%%PORTVERSION%%',
    'description': 'Yet another style preserving TOML library',
    'long_description': long_description,
    'license': 'MIT',
    'author': '',
    'author_email': 'Frost Ming <mianghong@gmail.com>,Sébastien Eustace <sebastien@eustace.io>',
    'maintainer': None,
    'maintainer_email': None,
    'url': '',
    'packages': [
        'atoml',
    ],
    'package_data': {'': ['*']},
    'long_description_content_type': 'text/markdown',
    'classifiers': [
        'License :: OSI Approved :: MIT License',
        'Programming Language :: Python :: 3',
        'Programming Language :: Python :: 3.6',
        'Programming Language :: Python :: 3.7',
        'Programming Language :: Python :: 3.8',
        'Programming Language :: Python :: 3.9',
    ],
    'python_requires': '>=3.6',

}


setup(**setup_kwargs)
