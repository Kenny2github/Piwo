from sys import argv
from os import env
from cgi import escape as hsc

argv = argv[1:]
MW_ROOT = env.get('MW_ROOT', None)
GRAM_NAME = env.get('MW_GRAM_NAME', None)
