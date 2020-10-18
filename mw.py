from sys import argv
from os import environ as env
from cgi import escape as hsc

MW_ROOT = env.get('MW_ROOT', None)
GRAM_NAME = env.get('MW_GRAM_NAME', None)
argv[0] = '#piwo:' + GRAM_NAME
